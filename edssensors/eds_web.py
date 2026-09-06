#!/usr/bin/env python3
"""Write latest eds output as HTML to Apache web root every 5 s."""

import re, time, subprocess
from datetime import datetime

OUTPUT_FILE = "/tmp/eds_output.txt"
HTML_FILE   = "/var/www/html/eds_status.html"
FRAME_SEP   = "\x1b[2J\x1b[H"
ANSI        = re.compile(r'\x1B\[[0-9;]*[mJH]')

# DB credentials (match edssensors/edsServerHandlerConf.txt: local mysql, dbuser).
DB_USER = "dbuser"
DB_PWD  = "kmjmkm54C#"
DB_NAME = "mydb"


def load_id2_map():
    """Return {sensorid: id2} from sensorconfig via the mysql CLI.

    id2 is the deterministic (FNV-1a) sensor id. Keyed on the old sensorid that
    eds currently prints, so the status page can show both. Returns {} on any
    error or if the id2 column does not exist yet."""
    try:
        out = subprocess.run(
            ["mysql", "-u", DB_USER, "-p" + DB_PWD, DB_NAME, "-N", "-B", "-e",
             "SELECT sensorid, id2 FROM sensorconfig "
             "WHERE id2 IS NOT NULL AND id2 <> ''"],
            capture_output=True, timeout=5,
        )
        mapping = {}
        for line in out.stdout.decode("utf-8", "replace").splitlines():
            parts = line.split("\t")
            if len(parts) == 2:
                mapping[parts[0]] = parts[1]
        return mapping
    except Exception:
        return {}


def to_html(text, id2_map):
    rows = []
    for line in text.splitlines():
        line = line.rstrip()
        if not line:
            continue
        m = re.match(r'^(\S+)\s+\(([^)]+)\)\s+thread id:\s+(\S+)$', line)
        if m:
            rows.append(f'<tr class="host"><td colspan="6"><b>{m.group(1)}</b>'
                        f' &nbsp;{m.group(2)}&nbsp; thread {m.group(3)}</td></tr>')
            continue
        m = re.match(r'^(\S+)\s+(\d+)\s+(\S+)\s*:\s*(\S+)\s+\((\w+)\)$', line)
        if m:
            sensorid = m.group(2)
            id2 = id2_map.get(sensorid, "")
            rows.append(f'<tr><td>{m.group(1)}</td><td>{sensorid}</td>'
                        f'<td>{id2}</td>'
                        f'<td>{m.group(3)}</td><td>{m.group(4)}</td>'
                        f'<td>{m.group(5)}</td></tr>')
            continue
        rows.append(f'<tr class="misc"><td colspan="6"><pre>{line}</pre></td></tr>')
    return "\n".join(rows)


HTML = """\
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8">
<meta http-equiv="refresh" content="60">
<title>EDS Sensors</title>
<style>
body{{font-family:monospace;background:#111;color:#ccc;margin:1em}}
h2{{color:#8f8}}
table{{border-collapse:collapse;width:100%}}
th{{background:#333;color:#8f8;padding:4px 8px;text-align:left}}
td{{padding:3px 8px;border-bottom:1px solid #222}}
tr.host td{{background:#1a2a1a;color:#8f8;font-weight:bold;padding:6px 8px}}
tr.misc td{{color:#888}}
tr:hover td{{background:#1e1e1e}}
.ts{{color:#555;font-size:.85em}}
</style></head><body>
<h2>EDS Sensor Data</h2>
<p class="ts">Updated: {ts} | auto-refresh 60 s</p>
<table><tr><th>Type</th><th>ID</th><th>ID2</th><th>Name</th><th>Value</th><th>Unit</th></tr>
{rows}
</table></body></html>
"""

while True:
    try:
        with open(OUTPUT_FILE) as f:
            buf = f.read()
        parts = buf.split(FRAME_SEP)
        if len(parts) >= 2:
            frame = ANSI.sub("", parts[-2])
            id2_map = load_id2_map()
            html = HTML.format(ts=datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                               rows=to_html(frame, id2_map))
            with open(HTML_FILE, "w") as f:
                f.write(html)
    except FileNotFoundError:
        pass
    time.sleep(5)
