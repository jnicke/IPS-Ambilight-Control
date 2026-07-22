#!/usr/bin/env python3

import asyncio
import json
import logging
import time
from pathlib import Path
from typing import Any

import aiohttp
from aiohttp import web


BASE_DIR = Path(__file__).resolve().parent
CONFIG_FILE = BASE_DIR / "config.json"

DEFAULT_CONFIG: dict[str, Any] = {
    "identifier": "27E85A46-3DF8-48F5-9608-58D33E7CE76B",
    "address": "192.168.178.145",
    "listen": "0.0.0.0",
    "port": 8091,
    "atvscript": "/home/pi/pyatv/bin/atvscript",
    "reconnect_delay": 5,
    "log_level": "INFO",
    "webhook_url": "",
    "webhook_timeout": 5
}

# Nur Änderungen an diesen Feldern lösen einen WebHook-Push aus.
# Positions-Updates würden sonst im Sekundentakt Ereignisse erzeugen.
PUSH_SIGNIFICANT_KEYS = ("online", "power", "state", "app", "app_id", "app_current")


class AppleTVMonitor:
    def __init__(self, config: dict[str, Any]) -> None:
        self.config = config
        self.process: asyncio.subprocess.Process | None = None
        self.monitor_task: asyncio.Task[None] | None = None
        self.push_task: asyncio.Task[None] | None = None
        self.push_queue: asyncio.Queue[dict[str, Any]] = asyncio.Queue()
        self.last_pushed_signature: tuple[Any, ...] | None = None
        self.stopping = False

        self.status: dict[str, Any] = {
            "online": False,
            "power": "unknown",
            "state": "offline",
            "device_state": "Unknown",
            "media_type": "Unknown",
            "title": "",
            "artist": "",
            "album": "",
            "genre": "",
            "app": "",
            "app_id": "",
            "app_current": False,
            "position": 0.0,
            "total_time": 0.0,
            "repeat": "Off",
            "shuffle": "Off",
            "updated": int(time.time()),
            "last_event": 0,
            "error": "",
            "identifier": str(config["identifier"]),
            "address": str(config.get("address", ""))
        }

    def set_status(self, **values: Any) -> None:
        self.status.update(values)
        self.status["updated"] = int(time.time())
        self.queue_push_if_changed()

    def queue_push_if_changed(self) -> None:
        if not str(self.config.get("webhook_url", "")).strip():
            return

        signature = tuple(
            self.status.get(key) for key in PUSH_SIGNIFICANT_KEYS
        )

        if signature == self.last_pushed_signature:
            return

        self.last_pushed_signature = signature
        self.push_queue.put_nowait(dict(self.status))

    async def push_loop(self) -> None:
        url = str(self.config.get("webhook_url", "")).strip()

        if not url:
            return

        timeout = aiohttp.ClientTimeout(
            total=max(1, int(self.config.get("webhook_timeout", 5)))
        )

        async with aiohttp.ClientSession(timeout=timeout) as session:
            while not self.stopping:
                snapshot = await self.push_queue.get()

                try:
                    async with session.post(url, json=snapshot) as response:
                        if response.status >= 300:
                            logging.warning(
                                "WebHook-Push abgelehnt: HTTP %d",
                                response.status
                            )
                        else:
                            logging.debug(
                                "WebHook-Push gesendet: state=%s app=%s",
                                snapshot.get("state"),
                                snapshot.get("app")
                            )
                except asyncio.CancelledError:
                    raise
                except Exception as exception:  # noqa: BLE001
                    logging.warning(
                        "WebHook-Push fehlgeschlagen: %s",
                        exception
                    )

    @staticmethod
    def normalize_state(value: Any) -> str:
        if value is None:
            return "unknown"

        state = str(value).strip().lower()

        mapping = {
            "playing": "playing",
            "paused": "paused",
            "stopped": "idle",
            "idle": "idle",
            "loading": "loading",
            "seeking": "seeking",
            "no media": "idle",
            "nomedia": "idle",
            "unknown": "unknown"
        }

        return mapping.get(state, state)

    @staticmethod
    def normalize_power(value: Any) -> str:
        if isinstance(value, bool):
            return "on" if value else "off"

        power = str(value).strip().lower()

        if power in {"on", "true", "1", "poweredon"}:
            return "on"

        if power in {"off", "false", "0", "poweredoff", "standby"}:
            return "off"

        return "unknown"

    @staticmethod
    def first_value(data: dict[str, Any], *keys: str, default: Any = "") -> Any:
        for key in keys:
            if key in data and data[key] is not None:
                return data[key]

        return default

    def process_event(self, event: dict[str, Any]) -> None:
        logging.debug("Apple-TV-Ereignis: %s", json.dumps(event, ensure_ascii=False))

        result = str(event.get("result", "")).lower()

        if result == "failure":
            connection = str(event.get("connection", "")).lower()
            error = str(
                event.get("exception")
                or event.get("error")
                or connection
                or "Unbekannter Fehler"
            )

            self.set_status(
                online=False,
                state="offline",
                error=error,
                last_event=int(time.time())
            )
            return

        if event.get("connection") in {"closed", "lost"}:
            self.set_status(
                online=False,
                state="offline",
                error=str(event.get("connection")),
                last_event=int(time.time())
            )
            return

        updates: dict[str, Any] = {
            "online": True,
            "error": "",
            "last_event": int(time.time())
        }

        if "power_state" in event:
            power = self.normalize_power(event["power_state"])
            updates["power"] = power

            if power == "off":
                updates["state"] = "standby"
                updates["device_state"] = "Standby"

        device_state = self.first_value(
            event,
            "device_state",
            "deviceState",
            "play_state",
            "state",
            default=None
        )

        if device_state is not None:
            updates["device_state"] = str(device_state)
            updates["state"] = self.normalize_state(device_state)

        media_type = self.first_value(
            event,
            "media_type",
            "mediaType",
            default=None
        )

        if media_type is not None:
            updates["media_type"] = str(media_type)

        title = self.first_value(event, "title", default=None)
        if title is not None:
            updates["title"] = str(title)

        artist = self.first_value(event, "artist", default=None)
        if artist is not None:
            updates["artist"] = str(artist)

        album = self.first_value(event, "album", default=None)
        if album is not None:
            updates["album"] = str(album)

        genre = self.first_value(event, "genre", default=None)
        if genre is not None:
            updates["genre"] = str(genre)

        app = self.first_value(
            event,
            "app",
            "app_name",
            "appName",
            default=None
        )

        if isinstance(app, dict):
            updates["app"] = str(app.get("name", ""))
            updates["app_id"] = str(
                app.get("identifier")
                or app.get("bundle_id")
                or ""
            )
        elif app is not None:
            updates["app"] = str(app)

        app_id = self.first_value(
            event,
            "app_id",
            "app_identifier",
            "bundle_id",
            default=None
        )

        if app_id is not None:
            updates["app_id"] = str(app_id)
        # Der Apple TV meldet beim Verlassen einer Wiedergabe haeufig
        # app_id als leeren String, waehrend der App-Name im Event fehlt
        # (app: null). Der zuletzt bekannte Name bleibt dann erhalten,
        # wird aber als nicht mehr aktuell gekennzeichnet.
        effective_app_id = updates.get("app_id", self.status["app_id"])
        effective_app = updates.get("app", self.status["app"])
        updates["app_current"] = bool(
            str(effective_app_id).strip()
            and str(effective_app).strip()
        )

        # Bei einem App-Wechsel veraltete Metadaten der vorherigen App
        # zurücksetzen, sofern das Ereignis keine neuen Werte liefert.
        # Ohne diesen Schritt bliebe z. B. der Titel der alten App stehen.
        new_app_id = updates.get("app_id")

        if (
            new_app_id is not None
            and new_app_id != self.status["app_id"]
        ):
            for key, default in (
                ("title", ""),
                ("artist", ""),
                ("album", ""),
                ("genre", ""),
                ("media_type", "Unknown"),
                ("position", 0.0),
                ("total_time", 0.0)
            ):
                updates.setdefault(key, default)

        position = self.first_value(
            event,
            "position",
            "elapsed_time",
            default=None
        )

        if position is not None:
            try:
                updates["position"] = float(position)
            except (TypeError, ValueError):
                pass

        total_time = self.first_value(
            event,
            "total_time",
            "duration",
            default=None
        )

        if total_time is not None:
            try:
                updates["total_time"] = float(total_time)
            except (TypeError, ValueError):
                pass

        repeat = self.first_value(event, "repeat", default=None)
        if repeat is not None:
            updates["repeat"] = str(repeat)

        shuffle = self.first_value(event, "shuffle", default=None)
        if shuffle is not None:
            updates["shuffle"] = str(shuffle)

        self.set_status(**updates)

    async def stop_process(self) -> None:
        process = self.process

        if process is None:
            return

        self.process = None

        if process.stdin is not None:
            try:
                process.stdin.close()
            except Exception:  # noqa: BLE001
                pass

        if process.returncode is None:
            process.terminate()

            try:
                await asyncio.wait_for(process.wait(), timeout=5)
            except asyncio.TimeoutError:
                process.kill()
                await process.wait()

    async def run_atvscript(self) -> None:
        command = [
            str(self.config["atvscript"]),
            "--id",
            str(self.config["identifier"])
        ]

        credentials = str(
            self.config.get("companion_credentials") or ""
        ).strip()

        if credentials:
            command += ["--companion-credentials", credentials]

        command.append("push_updates")
        logging.info("Starte Apple-TV-Überwachung: %s", " ".join(command))

        # Wichtig: atvscript push_updates beendet sich bei EOF auf stdin
        # ("Press ENTER to abort"). Unter systemd wäre stdin /dev/null und
        # damit sofort EOF. Deshalb bekommt der Prozess eine offene Pipe,
        # in die nie geschrieben wird.
        process = await asyncio.create_subprocess_exec(
            *command,
            stdin=asyncio.subprocess.PIPE,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )

        # Lokale Referenz verwenden: stop_process() setzt self.process
        # beim Herunterfahren parallel auf None.
        self.process = process

        if process.stdout is None or process.stderr is None:
            raise RuntimeError("atvscript konnte nicht gestartet werden")

        stderr_task = asyncio.create_task(
            self.read_stderr(process.stderr)
        )

        try:
            while not self.stopping:
                line = await process.stdout.readline()

                if not line:
                    break

                text = line.decode("utf-8", errors="replace").strip()

                if not text:
                    continue

                logging.debug("atvscript stdout: %s", text)

                try:
                    event = json.loads(text)
                except json.JSONDecodeError:
                    logging.warning(
                        "Ungültige JSON-Ausgabe von atvscript: %s",
                        text
                    )
                    continue

                if isinstance(event, dict):
                    self.process_event(event)

            return_code = await process.wait()

            if not self.stopping:
                self.set_status(
                    online=False,
                    state="offline",
                    error=f"atvscript beendet, Rückgabewert {return_code}"
                )

        finally:
            stderr_task.cancel()

            try:
                await stderr_task
            except asyncio.CancelledError:
                pass

            await self.stop_process()

    async def read_stderr(
        self,
        stream: asyncio.StreamReader
    ) -> None:
        while not self.stopping:
            line = await stream.readline()

            if not line:
                break

            message = line.decode("utf-8", errors="replace").strip()

            if message:
                logging.warning("atvscript stderr: %s", message)

    async def monitor_loop(self) -> None:
        reconnect_delay = max(
            1,
            int(self.config.get("reconnect_delay", 5))
        )

        while not self.stopping:
            try:
                await self.run_atvscript()
            except asyncio.CancelledError:
                break
            except Exception as exception:
                logging.exception("Fehler in der Apple-TV-Überwachung")

                self.set_status(
                    online=False,
                    state="offline",
                    error=str(exception)
                )

            if not self.stopping:
                logging.info(
                    "Neuer Verbindungsversuch in %d Sekunden",
                    reconnect_delay
                )
                await asyncio.sleep(reconnect_delay)

    async def start(self) -> None:
        self.stopping = False
        self.monitor_task = asyncio.create_task(self.monitor_loop())

        if str(self.config.get("webhook_url", "")).strip():
            self.push_task = asyncio.create_task(self.push_loop())
            logging.info(
                "WebHook-Push aktiv: %s",
                self.config["webhook_url"]
            )

    async def stop(self) -> None:
        self.stopping = True

        await self.stop_process()

        for task in (self.monitor_task, self.push_task):
            if task is None:
                continue

            task.cancel()

            try:
                await task
            except asyncio.CancelledError:
                pass

        self.monitor_task = None
        self.push_task = None


def load_config() -> dict[str, Any]:
    config = DEFAULT_CONFIG.copy()

    if CONFIG_FILE.exists():
        with CONFIG_FILE.open("r", encoding="utf-8") as file:
            loaded = json.load(file)

        if not isinstance(loaded, dict):
            raise ValueError("config.json muss ein JSON-Objekt enthalten")

        config.update(loaded)

    required = ["identifier", "listen", "port", "atvscript"]

    for key in required:
        if key not in config or config[key] in (None, ""):
            raise ValueError(f"Konfiguration fehlt: {key}")

    return config


async def status_handler(request: web.Request) -> web.Response:
    monitor: AppleTVMonitor = request.app["monitor"]
    return web.json_response(monitor.status)


async def health_handler(request: web.Request) -> web.Response:
    monitor: AppleTVMonitor = request.app["monitor"]

    response = {
        "service": "appletv-monitor",
        "running": True,
        "appletv_online": monitor.status["online"],
        "updated": monitor.status["updated"]
    }

    return web.json_response(response)


async def startup_handler(app: web.Application) -> None:
    monitor: AppleTVMonitor = app["monitor"]
    await monitor.start()


async def shutdown_handler(app: web.Application) -> None:
    monitor: AppleTVMonitor = app["monitor"]
    await monitor.stop()


def create_app(config: dict[str, Any]) -> web.Application:
    app = web.Application()
    app["monitor"] = AppleTVMonitor(config)

    app.router.add_get("/status", status_handler)
    app.router.add_get("/health", health_handler)

    app.on_startup.append(startup_handler)
    app.on_cleanup.append(shutdown_handler)

    return app


def main() -> None:
    config = load_config()

    logging.basicConfig(
        level=getattr(
            logging,
            str(config.get("log_level", "INFO")).upper(),
            logging.INFO
        ),
        format="%(asctime)s %(levelname)s %(message)s"
    )

    logging.info(
        "Starte Apple-TV-Monitor für %s auf %s:%s",
        config["identifier"],
        config["listen"],
        config["port"]
    )

    app = create_app(config)

    web.run_app(
        app,
        host=str(config["listen"]),
        port=int(config["port"]),
        handle_signals=True,
        print=None
    )


if __name__ == "__main__":
    main()
