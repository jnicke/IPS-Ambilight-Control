#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

sudo install -d -m 0755 /opt/appletv-monitor
sudo install -m 0755 "$SCRIPT_DIR/appletv-monitor.py" /opt/appletv-monitor/appletv-monitor.py
sudo install -m 0644 "$SCRIPT_DIR/appletv-monitor.service" /etc/systemd/system/appletv-monitor.service
sudo systemctl daemon-reload
sudo systemctl enable --now appletv-monitor.service
sudo systemctl --no-pager --full status appletv-monitor.service
