#!/usr/bin/env python3
"""Apple TV status bridge for IPS-Ambilight-Control.

Runs atvscript from the same pyatv virtual environment, consumes its JSON push
updates and exposes the latest normalized state via a small HTTP endpoint.
"""

from __future__ import annotations

import argparse
import asyncio
import json
import logging
import signal
import time
from dataclasses import asdict, dataclass
from http import HTTPStatus
from typing import Any

from aiohttp import web

LOG = logging.getLogger("appletv-monitor")


@dataclass
class AppleTVStatus:
    online: bool = False
    power: str = "unknown"
    state: str = "offline"
    device_state: str = "Unknown"
    media_type: str = "Unknown"
    title: str = ""
    artist: str = ""
    album: str = ""
    app: str = ""
    app_id: str = ""
    position: float = 0.0
    total_time: float = 0.0
    updated_at: int = 0
    error: str = ""


class AppleTVMonitor:
    def __init__(self, address: str, atvscript: str, reconnect_delay: int) -> None:
        self.address = address
        self.atvscript = atvscript
        self.reconnect_delay = max(2, reconnect_delay)
        self.status = AppleTVStatus()
        self.process: asyncio.subprocess.Process | None = None
        self.task: asyncio.Task[None] | None = None
        self.stopping = False

    def snapshot(self) -> dict[str, Any]:
        data = asdict(self.status)
        data["address"] = self.address
        return data

    async def start(self) -> None:
        self.task = asyncio.create_task(self._run(), name="pyatv-push-monitor")

    async def stop(self) -> None:
        self.stopping = True
        if self.process and self.process.returncode is None:
            self.process.terminate()
            try:
                await asyncio.wait_for(self.process.wait(), timeout=5)
            except asyncio.TimeoutError:
                self.process.kill()
                await self.process.wait()
        if self.task:
            self.task.cancel()
            try:
                await self.task
            except asyncio.CancelledError:
                pass

    async def _run(self) -> None:
        while not self.stopping:
            try:
                await self._run_once()
            except asyncio.CancelledError:
                raise
            except Exception as exc:  # noqa: BLE001
                LOG.exception("Apple TV monitor failed")
                self._mark_offline(str(exc))
            if not self.stopping:
                await asyncio.sleep(self.reconnect_delay)

    async def _run_once(self) -> None:
        command = [
            self.atvscript,
            "--address",
            self.address,
            "push_updates",
        ]
        LOG.info("Starting: %s", " ".join(command))
        self.process = await asyncio.create_subprocess_exec(
            *command,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        assert self.process.stdout is not None
        assert self.process.stderr is not None

        stderr_task = asyncio.create_task(self._consume_stderr(self.process.stderr))
        try:
            while not self.stopping:
                line = await self.process.stdout.readline()
                if not line:
                    break
                self._handle_line(line.decode("utf-8", errors="replace").strip())
        finally:
            stderr_task.cancel()
            if self.process.returncode is None:
                self.process.terminate()
            await self.process.wait()
            if not self.stopping:
                self._mark_offline(f"atvscript exited with code {self.process.returncode}")

    async def _consume_stderr(self, stream: asyncio.StreamReader) -> None:
        while True:
            line = await stream.readline()
            if not line:
                return
            LOG.warning("atvscript: %s", line.decode("utf-8", errors="replace").strip())

    def _handle_line(self, line: str) -> None:
        if not line:
            return
        try:
            payload = json.loads(line)
        except json.JSONDecodeError:
            LOG.debug("Ignoring non-JSON output: %s", line)
            return

        if payload.get("result") == "failure":
            connection = str(payload.get("connection", "failed"))
            error = str(payload.get("exception", connection))
            self._mark_offline(error)
            return

        self.status.online = True
        self.status.error = ""
        self.status.updated_at = int(time.time())

        power_state = payload.get("power_state")
        if power_state is not None:
            self.status.power = self._normalize_power(power_state)

        self._update_text(payload, "device_state", "device_state")
        self._update_text(payload, "media_type", "media_type")
        self._update_text(payload, "title", "title")
        self._update_text(payload, "artist", "artist")
        self._update_text(payload, "album", "album")
        self._update_text(payload, "app", "app")
        self._update_text(payload, "app_id", "app_id")
        self._update_number(payload, "position", "position")
        self._update_number(payload, "total_time", "total_time")

        self.status.state = self._normalize_state(
            payload.get("device_state", self.status.device_state),
            self.status.power,
        )
        LOG.debug("Status: %s", self.snapshot())

    def _mark_offline(self, error: str) -> None:
        self.status.online = False
        self.status.power = "unknown"
        self.status.state = "offline"
        self.status.updated_at = int(time.time())
        self.status.error = error

    def _update_text(self, payload: dict[str, Any], source: str, target: str) -> None:
        value = payload.get(source)
        if value is not None:
            setattr(self.status, target, str(value))

    def _update_number(self, payload: dict[str, Any], source: str, target: str) -> None:
        value = payload.get(source)
        if isinstance(value, (int, float)):
            setattr(self.status, target, float(value))

    @staticmethod
    def _normalize_power(value: Any) -> str:
        text = str(value).strip().lower()
        if text in {"on", "true", "1", "powerstate.on"}:
            return "on"
        if text in {"off", "false", "0", "powerstate.off"}:
            return "off"
        return "unknown"

    @staticmethod
    def _normalize_state(value: Any, power: str) -> str:
        if power == "off":
            return "standby"
        text = str(value).strip().lower().replace("device_state.", "")
        if text in {"playing", "play", "seeking"}:
            return "playing"
        if text in {"paused", "pause"}:
            return "paused"
        if text in {"idle", "stopped", "loading", "no_media", "unknown"}:
            return "idle"
        return text or "idle"


async def create_app(args: argparse.Namespace) -> web.Application:
    monitor = AppleTVMonitor(args.address, args.atvscript, args.reconnect_delay)
    await monitor.start()

    app = web.Application()
    app["monitor"] = monitor

    async def status_handler(_: web.Request) -> web.Response:
        return web.json_response(monitor.snapshot())

    async def health_handler(_: web.Request) -> web.Response:
        status = monitor.snapshot()
        code = HTTPStatus.OK if status["online"] else HTTPStatus.SERVICE_UNAVAILABLE
        return web.json_response(status, status=code)

    async def on_cleanup(_: web.Application) -> None:
        await monitor.stop()

    app.router.add_get("/status", status_handler)
    app.router.add_get("/health", health_handler)
    app.on_cleanup.append(on_cleanup)
    return app


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Expose Apple TV state as JSON")
    parser.add_argument("--address", required=True, help="Apple TV IP address")
    parser.add_argument("--listen", default="0.0.0.0", help="HTTP listen address")
    parser.add_argument("--port", type=int, default=8091, help="HTTP listen port")
    parser.add_argument(
        "--atvscript",
        default="/home/pi/pyatv/bin/atvscript",
        help="Path to atvscript in the pyatv virtual environment",
    )
    parser.add_argument("--reconnect-delay", type=int, default=5)
    parser.add_argument("--verbose", action="store_true")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    logging.basicConfig(
        level=logging.DEBUG if args.verbose else logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )
    web.run_app(create_app(args), host=args.listen, port=args.port, print=None)


if __name__ == "__main__":
    main()
