#!/usr/bin/python3
import json
import os
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.request import urlopen

CONFIG_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config.json")
STATUS_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "status.json")
SENSOR_URL = "http://ws-gateway/get_livedata_info?"
PORT = 8080

HTML_PAGE = """<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pot Config</title>
<style>
body { font-family: sans-serif; max-width: 800px; margin: 0 auto; padding: 1em; background: #000; color: #eee; }
.pot { border: 1px solid #555; border-radius: 8px; padding: 1em; margin-bottom: 1em; position: relative; }
.pot.inactive { opacity: 0.5; }
.pot h3 { margin: 0 0 0.5em 0; color: #0f0; }
.battery { position: absolute; top: 1em; right: 1em; width: 24px; height: 12px; border: 1px solid #888; border-radius: 2px; }
.battery::after { content: ''; position: absolute; right: -3px; top: 3px; width: 2px; height: 4px; background: #888; border-radius: 0 1px 1px 0; }
.battery .level { position: absolute; left: 1px; top: 1px; bottom: 1px; border-radius: 1px; }
.water-icon { font-size: 1.2em; animation: pulse 1s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
.row { display: flex; align-items: center; margin: 0.4em 0; }
.row label { width: 120px; }
.row .slider-wrap { flex: 1; position: relative; }
.row .slider-wrap input[type=range] { width: 100%; accent-color: #0a0; }
.row input[type=checkbox] { accent-color: #0a0; width: 20px; height: 20px; }
.row .val { width: 40px; text-align: right; margin-left: 0.5em; }
.levels { position: relative; }
.levels .hum-line { position: absolute; top: 0; bottom: 0; width: 2px; background: #888; pointer-events: none; }
.levels .hum-label { position: absolute; top: -1.2em; color: #888; font-size: 0.75em; transform: translateX(-50%); pointer-events: none; }
button { font-size: 1.2em; padding: 0.5em 2em; margin-top: 1em; cursor: pointer; background: #0a0; color: #000; border: none; border-radius: 4px; }
button:hover { background: #0c0; }
.msg { margin-left: 1em; color: #0f0; }
</style>
</head>
<body>
<h1>Watering Configuration</h1>
<div id="pots"></div>
<div id="extras"></div>
<button onclick="save()">Save</button>
<span class="msg" id="msg"></span>
<script>
var config = __CONFIG__;
var humidity = __HUMIDITY__;
var sensors = __SENSORS__;
var pumpStatus = __STATUS__;

function batteryHtml(level) {
  if (level === null || level === undefined) return '';
  var l = parseInt(level);
  var pct = l * 20;
  var color = pct <= 20 ? '#f00' : pct <= 40 ? '#fa0' : '#0a0';
  return '<div class="battery"><div class="level" style="width:' + (pct > 100 ? 100 : pct) + '%;background:' + color + '"></div></div>';
}

function render() {
  var html = '';
  config.forEach(function(p, i) {
    var active = parseInt(p.potActive);
    var hum = (i < humidity.length) ? humidity[i] : null;
    var humPct = (hum !== null && hum !== '--') ? parseInt(hum) : null;
    html += '<div class="pot ' + (active ? '' : 'inactive') + '">';
    var bat = (i < sensors.length) ? sensors[i].battery : null;
    var isWatering = pumpStatus.watering && pumpStatus.watering.indexOf(i) >= 0;
    var isTesting = pumpStatus.testing && isWatering;
    html += batteryHtml(bat);
    html += '<h3>' + p.name + ' (CH' + p.channel + ')' + (humPct !== null ? ' <span style="color:#888;font-size:0.85em">Current: ' + humPct + '%</span>' : '') + (isWatering ? ' <span class="water-icon">' + (isTesting ? '🔧' : '💧') + '</span>' : '') + '</h3>';
    html += '<div class="row"><label>Active</label><input type="checkbox" ' + (active ? 'checked' : '') + ' onchange="toggle(' + i + ', this.checked)"></div>';
    html += '<div class="levels">';
    if (humPct !== null) {
      var leftPos = 'calc(120px + (100% - 120px - 50px) * ' + humPct + ' / 100)';
      html += '<div class="hum-line" style="left:' + leftPos + '"></div>';
      html += '<div class="hum-label" style="left:' + leftPos + '">' + humPct + '%</div>';
    }
    html += '<div class="row"><label>Dry level</label><div class="slider-wrap"><input type="range" min="0" max="99" value="' + p.potDry + '" oninput="setDry(' + i + ', this.value)"></div><span class="val">' + p.potDry + '</span></div>';
    html += '<div class="row"><label>Wet level</label><div class="slider-wrap"><input type="range" min="1" max="100" value="' + p.potWet + '" oninput="setWet(' + i + ', this.value)"></div><span class="val">' + p.potWet + '</span></div>';
    html += '</div>';
    html += '<div class="row"><label>Duration (s)</label><div class="slider-wrap"><input type="range" min="1" max="300" value="' + p.watering_duration + '" oninput="setDur(' + i + ', this.value)"></div><span class="val" id="dur' + i + '">' + p.watering_duration + '</span></div>';
    html += '</div>';
  });
  document.getElementById('pots').innerHTML = html;
  // Render extra sensors (display only)
  var extra = '';
  for (var i = config.length; i < sensors.length; i++) {
    var s = sensors[i];
    var hum = s.humidity ? s.humidity.replace('%', '') : '--';
    extra += '<div class="pot">';
    extra += batteryHtml(s.battery);
    extra += '<h3>' + (s.name || '') + ' (CH' + s.channel + ') <span style="color:#888;font-size:0.85em">Current: ' + hum + '%</span></h3>';
    extra += '<div class="row" style="color:#888">Monitor only — not connected to watering</div>';
    extra += '</div>';
  }
  document.getElementById('extras').innerHTML = extra;
}

function setDry(i, v) {
  v = parseInt(v);
  config[i].potDry = '' + v;
  if (v >= parseInt(config[i].potWet)) {
    config[i].potWet = '' + (v + 1);
  }
  render();
}

function setWet(i, v) {
  v = parseInt(v);
  config[i].potWet = '' + v;
  if (v <= parseInt(config[i].potDry)) {
    config[i].potDry = '' + (v - 1);
  }
  render();
}

function setDur(i, v) {
  config[i].watering_duration = '' + parseInt(v);
  document.getElementById('dur' + i).textContent = v;
}

function toggle(i, checked) {
  config[i].potActive = checked ? '1' : '0';
  render();
}

function save() {
  fetch('/save', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({potConfig: config})
  }).then(function(r) {
    if (r.ok) {
      document.getElementById('msg').textContent = 'Saved!';
      setTimeout(function() { document.getElementById('msg').textContent = ''; }, 2000);
    } else {
      document.getElementById('msg').textContent = 'Error!';
      document.getElementById('msg').style.color = 'red';
    }
  });
}

render();

setInterval(function() {
  fetch('/humidity').then(function(r) { return r.json(); }).then(function(data) {
    humidity = data.humidity;
    sensors = data.sensors;
    pumpStatus = data.status;
    render();
  });
}, 10000);
</script>
</body>
</html>"""


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/' or self.path == '':
            config = self.load_config()
            humidity = self.get_humidity()
            sensors = self.get_sensors()
            status = self.get_status()
            page = HTML_PAGE.replace('__CONFIG__', json.dumps(config))
            page = page.replace('__HUMIDITY__', json.dumps(humidity))
            page = page.replace('__SENSORS__', json.dumps(sensors))
            page = page.replace('__STATUS__', json.dumps(status))
            self.send_response(200)
            self.send_header('Content-Type', 'text/html')
            self.end_headers()
            self.wfile.write(page.encode())
        elif self.path == '/humidity':
            sensors = self.get_sensors()
            humidity = [ch.get('humidity', '--').replace('%', '') for ch in sensors]
            status = self.get_status()
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({"humidity": humidity, "sensors": sensors, "status": status}).encode())
        else:
            self.send_response(404)
            self.end_headers()

    def do_POST(self):
        if self.path == '/save':
            length = int(self.headers.get('Content-Length', 0))
            body = self.rfile.read(length)
            try:
                data = json.loads(body)
                for p in data['potConfig']:
                    dry = int(p['potDry'])
                    wet = int(p['potWet'])
                    dur = int(p['watering_duration'])
                    if dry < 0 or dry > 99:
                        raise ValueError("potDry out of range")
                    if wet < 1 or wet > 100:
                        raise ValueError("potWet out of range")
                    if wet <= dry:
                        raise ValueError("potWet must be > potDry")
                    if dur < 1 or dur > 300:
                        raise ValueError("duration out of range")
                with open(CONFIG_PATH, 'w') as f:
                    json.dump(data, f, indent='\t')
                os.system("filetool.sh -b")
                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(b'{"status":"ok"}')
            except Exception as e:
                self.send_response(400)
                self.send_header('Content-Type', 'application/json')
                self.end_headers()
                self.wfile.write(json.dumps({"error": str(e)}).encode())
        else:
            self.send_response(404)
            self.end_headers()

    def load_config(self):
        try:
            with open(CONFIG_PATH, 'r') as f:
                return json.load(f)['potConfig']
        except Exception:
            return []

    def get_humidity(self):
        try:
            response = urlopen(SENSOR_URL, timeout=5)
            data = json.loads(response.read())
            return [ch['humidity'].replace('%', '') for ch in data.get('ch_soil', [])]
        except Exception:
            return []

    def get_sensors(self):
        try:
            response = urlopen(SENSOR_URL, timeout=5)
            data = json.loads(response.read())
            return data.get('ch_soil', [])
        except Exception:
            return []

    def get_status(self):
        try:
            with open(STATUS_PATH, 'r') as f:
                return json.load(f)
        except Exception:
            return {"watering": [], "testing": False}

    def log_message(self, format, *args):
        print("[{}] {}".format(self.client_address[0], format % args))


if __name__ == '__main__':
    server = HTTPServer(('0.0.0.0', PORT), Handler)
    print("Serving on port {}".format(PORT))
    print("Config: {}".format(CONFIG_PATH))
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopped.")
        server.server_close()
