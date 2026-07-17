#!/usr/bin/python3
import json
import os
import time
import threading
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.request import urlopen

THERMAL_PATH = "/sys/class/thermal/thermal_zone0/temp"
CONFIG_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "config.json")
STATUS_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "status.json")
HISTORY_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)), "history.json")
SENSOR_URL = "http://ws-gateway/get_livedata_info?"
PORT = 8080
HISTORY_INTERVAL = 600  # 10 minutes
HISTORY_MAX_AGE = 7 * 24 * 3600  # 7 days in seconds

def get_cpu_temp():
    try:
        with open(THERMAL_PATH, 'r') as f:
            return round(int(f.read().strip()) / 1000, 1)
    except Exception:
        return None

def get_uptime():
    try:
        with open('/proc/uptime', 'r') as f:
            secs = int(float(f.read().split()[0]))
            days = secs // 86400
            hours = (secs % 86400) // 3600
            mins = (secs % 3600) // 60
            if days > 0:
                return "{}d {}h {}m".format(days, hours, mins)
            elif hours > 0:
                return "{}h {}m".format(hours, mins)
            else:
                return "{}m".format(mins)
    except Exception:
        return None

def load_history():
    try:
        with open(HISTORY_PATH, 'r') as f:
            return json.load(f)
    except Exception:
        return {}

def save_history(history):
    try:
        with open(HISTORY_PATH, 'w') as f:
            json.dump(history, f)
    except Exception:
        pass

def prune_history(history):
    cutoff = time.time() - HISTORY_MAX_AGE
    for ch in list(history.keys()):
        history[ch] = [p for p in history[ch] if p[0] >= cutoff]
        if not history[ch]:
            del history[ch]

def record_humidity():
    while True:
        try:
            response = urlopen(SENSOR_URL, timeout=10)
            data = json.loads(response.read())
            channels = data.get('ch_soil', [])
            now = time.time()
            history = load_history()
            for i, ch in enumerate(channels):
                key = str(i)
                if key not in history:
                    history[key] = []
                try:
                    val = int(ch.get('humidity', '--').replace('%', ''))
                except (ValueError, AttributeError):
                    val = None
                if val is not None:
                    history[key].append([now, val])
            prune_history(history)
            save_history(history)
        except Exception:
            pass
        time.sleep(HISTORY_INTERVAL)

HTML_PAGE = """<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pot Config</title>
<style>
body { font-family: sans-serif; max-width: 1000px; margin: 0 auto; padding: 1em; background: #000; color: #eee; }
.pot { border: 1px solid #555; border-radius: 8px; padding: 0.5em; margin-bottom: 0.5em; position: relative; font-size: 0.85em; cursor: pointer; user-select: none; min-height: 120px; }
.pot.inactive { opacity: 0.5; }
.pot h3 { margin: 0 0 0.5em 0; color: #0f0; }
.battery { position: absolute; top: 1em; right: 1em; width: 24px; height: 12px; border: 1px solid #888; border-radius: 2px; }
.battery::after { content: ''; position: absolute; right: -3px; top: 3px; width: 2px; height: 4px; background: #888; border-radius: 0 1px 1px 0; }
.battery .level { position: absolute; left: 1px; top: 1px; bottom: 1px; border-radius: 1px; }
.water-icon { font-size: 1.2em; animation: pulse 1s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
.row { display: flex; align-items: center; margin: 0.4em 0; }
.row label { width: 80px; }
.row .slider-wrap { flex: 1; position: relative; }
.row .slider-wrap input[type=range] { width: 100%; accent-color: #0a0; }
.dual-range { position: relative; height: 30px; margin-top: 14px; }
.hum-marker { position: absolute; top: -14px; transform: translateX(-50%); font-size: 0.7em; color: #0f0; pointer-events: none; white-space: nowrap; }
.hum-marker::after { content: '▼'; display: block; text-align: center; }
.dual-range input[type=range] { position: absolute; left: 0; top: 0; width: 100%; pointer-events: none; -webkit-appearance: none; appearance: none; background: transparent; height: 30px; margin: 0; }
.dual-range input[type=range]::-webkit-slider-thumb { pointer-events: all; -webkit-appearance: none; appearance: none; width: 18px; height: 18px; border-radius: 50%; cursor: pointer; }
.dual-range input[type=range]::-moz-range-thumb { pointer-events: all; width: 18px; height: 18px; border-radius: 50%; cursor: pointer; border: none; }
.dual-range .range-dry::-webkit-slider-thumb { background: #f80; }
.dual-range .range-dry::-moz-range-thumb { background: #f80; }
.dual-range .range-wet::-webkit-slider-thumb { background: #08f; }
.dual-range .range-wet::-moz-range-thumb { background: #08f; }
.dual-range .range-track { position: absolute; top: 13px; height: 4px; background: #333; width: 100%; border-radius: 2px; pointer-events: none; }
.dual-range .range-fill { position: absolute; top: 13px; height: 4px; background: linear-gradient(to right, #f80, #08f); border-radius: 2px; pointer-events: none; }
.dual-labels { display: flex; justify-content: space-between; font-size: 0.75em; color: #888; margin-top: -4px; }
.row input[type=checkbox] { accent-color: #0a0; width: 20px; height: 20px; }
.row .val { width: 40px; text-align: right; margin-left: 0.5em; }
button { font-size: 1.2em; padding: 0.5em 2em; margin-top: 1em; cursor: pointer; background: #0a0; color: #000; border: none; border-radius: 4px; }
button:hover { background: #0c0; }
.msg { margin-left: 1em; color: #0f0; }
.cpu-temp { position: fixed; bottom: 1em; right: 1em; font-size: 0.8em; color: #888; background: #111; border: 1px solid #333; border-radius: 4px; padding: 0.3em 0.6em; }
.cpu-temp.warn { color: #f80; }
.cpu-temp.crit { color: #f00; }
.chart-box { border: 1px solid #555; border-radius: 8px; padding: 0.5em; cursor: pointer; user-select: none; }
.chart-box canvas { width: 100%; height: 100%; display: block; }
.chart-box h3 { margin: 0 0 0.3em 0; color: #0f0; font-size: 0.85em; }
.pot-chart { overflow: hidden; }
</style>
</head>
<body>
<h1>Watering Configuration</h1>
<div id="countdown" style="color:#888;margin-bottom:1em"></div>
<div id="pots" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5em"></div>
<div id="extras" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5em"></div>
<button onclick="save()">Save</button>
<span class="msg" id="msg"></span>
<div class="cpu-temp" id="cputemp"></div><div class="cpu-temp" id="uptime" style="right:1em;bottom:3.2em"></div>
<script>
var config = __CONFIG__;
var humidity = __HUMIDITY__;
var sensors = __SENSORS__;
var pumpStatus = __STATUS__;
var cpuTemp = __CPU_TEMP__;
var uptime = __UPTIME__;
var chartVisible = {};
var chartHeight = {};

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
    html += '<div class="pot ' + (active ? '' : 'inactive') + '" id="pot' + i + '" ondblclick="showChart(' + i + ')">';
    var bat = (i < sensors.length) ? sensors[i].battery : null;
    var isWatering = pumpStatus.watering && pumpStatus.watering.indexOf(i) >= 0;
    var isTesting = pumpStatus.testing && isWatering;
    html += batteryHtml(bat);
    html += '<h3>' + p.name + ' (CH' + p.channel + ')' + (isWatering ? ' <span class="water-icon">' + (isTesting ? '🔧' : '💧') + '</span>' : '') + '</h3>';
    html += '<div class="pot-config" id="pcfg' + i + '">';
    html += '<div class="row"><label>Active</label><input type="checkbox" ' + (active ? 'checked' : '') + ' onchange="toggle(' + i + ', this.checked)"></div>';
    html += '<div class="row"><label>Dry / Wet</label><div class="slider-wrap"><div class="dual-range" id="dr' + i + '">' + (humPct !== null ? '<div class="hum-marker" id="hmark' + i + '" style="left:' + humPct + '%">' + humPct + '%</div>' : '') + '<div class="range-track"></div><div class="range-fill" id="fill' + i + '"></div><input type="range" class="range-dry" id="rdry' + i + '" min="0" max="99" value="' + p.potDry + '" oninput="setDry(' + i + ', this.value)"><input type="range" class="range-wet" id="rwet' + i + '" min="1" max="100" value="' + p.potWet + '" oninput="setWet(' + i + ', this.value)"></div><div class="dual-labels"><span id="ldry' + i + '">' + p.potDry + '% dry</span><span id="lwet' + i + '">' + p.potWet + '% wet</span></div></div></div>';
    html += '<div class="row"><label>Duration (s)</label><div class="slider-wrap"><input type="range" min="1" max="300" value="' + p.watering_duration + '" oninput="setDur(' + i + ', this.value)"></div><span class="val" id="dur' + i + '">' + p.watering_duration + '</span></div>';
    html += '</div>';
    html += '<div class="pot-chart" id="pchart' + i + '" style="display:none"><canvas id="canvas' + i + '" style="width:100%;height:100%"></canvas></div>';
    html += '</div>';
  });
  document.getElementById('pots').innerHTML = html;
  var extra = '';
  for (var i = config.length; i < sensors.length; i++) {
    var s = sensors[i];
    var hum = s.humidity ? s.humidity.replace('%', '') : '--';
    extra += '<div class="pot" ondblclick="showChart(' + i + ')">';
    extra += batteryHtml(s.battery);
    extra += '<h3>' + (s.name || '') + ' (CH' + s.channel + ') <span style="color:#888;font-size:0.85em">Current: ' + hum + '%</span></h3>';
    extra += '<div class="pot-config" id="pcfg' + i + '"><div class="row" style="color:#888">Monitor only — not connected to watering</div></div>';
    extra += '<div class="pot-chart" id="pchart' + i + '" style="display:none"><canvas id="canvas' + i + '" style="width:100%;height:100%"></canvas></div>';
    extra += '</div>';
  }
  document.getElementById('extras').innerHTML = extra;
}

function showChart(i) {
  var cfg = document.getElementById('pcfg' + i);
  var chart = document.getElementById('pchart' + i);
  if (!cfg || !chart) return;
  // If chart is already visible, hide it instead (toggle)
  if (chartVisible[i]) { hideChart(i); return; }
  var pot = cfg.parentElement;
  var h = pot.offsetHeight - 30;
  cfg.style.display = 'none';
  chart.style.display = 'block';
  chart.style.height = h + 'px';
  chartVisible[i] = true;
  chartHeight[i] = h;
  fetchAndDrawChart(i);
}

function hideChart(i) {
  var cfg = document.getElementById('pcfg' + i);
  var chart = document.getElementById('pchart' + i);
  if (!cfg || !chart) return;
  cfg.style.display = '';
  chart.style.display = 'none';
  chart.style.height = '';
  chartVisible[i] = false;
}

function restoreCharts() {
  for (var i in chartVisible) {
    if (chartVisible[i]) {
      var cfg = document.getElementById('pcfg' + i);
      var chart = document.getElementById('pchart' + i);
      if (cfg && chart) {
        var pot = cfg.parentElement;
        var h = chartHeight[i] || (pot.offsetHeight - 30);
        cfg.style.display = 'none';
        chart.style.display = 'block';
        chart.style.height = h + 'px';
        fetchAndDrawChart(parseInt(i));
      }
    }
  }
}

function fetchAndDrawChart(i) {
  fetch('/history?ch=' + i).then(function(r) { return r.json(); }).then(function(data) {
    drawChart(i, data);
  });
}

function drawChart(idx, points) {
  var canvas = document.getElementById('canvas' + idx);
  if (!canvas) return;
  canvas.ondblclick = function(e) { e.stopPropagation(); hideChart(idx); };
  var dpr = window.devicePixelRatio || 1;
  var rect = canvas.parentElement.getBoundingClientRect();
  var w = rect.width - 10;
  var h = rect.height || 150;
  canvas.width = w * dpr;
  canvas.height = h * dpr;
  canvas.style.width = w + 'px';
  canvas.style.height = h + 'px';
  var ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);
  ctx.fillStyle = '#111';
  ctx.fillRect(0, 0, w, h);

  if (!points || points.length === 0) {
    ctx.fillStyle = '#888';
    ctx.font = '12px sans-serif';
    ctx.fillText('No history data available', w/2 - 70, h/2);
    return;
  }

  var now = Date.now() / 1000;
  var span = 7 * 24 * 3600;
  var tMin = now - span;
  var tMax = now;
  var vMin = 0;
  var vMax = 100;
  var padL = 30, padR = 10, padT = 15, padB = 25;
  var cw = w - padL - padR;
  var ch2 = h - padT - padB;

  // Grid lines and labels
  ctx.strokeStyle = '#333';
  ctx.lineWidth = 0.5;
  ctx.fillStyle = '#888';
  ctx.font = '9px sans-serif';
  for (var v = 0; v <= 100; v += 25) {
    var y = padT + ch2 - (v / 100) * ch2;
    ctx.beginPath(); ctx.moveTo(padL, y); ctx.lineTo(padL + cw, y); ctx.stroke();
    ctx.fillText(v + '%', 2, y + 3);
  }
  // Day labels
  for (var d = 0; d < 7; d++) {
    var t = tMin + d * 24 * 3600;
    var x = padL + ((t - tMin) / span) * cw;
    ctx.beginPath(); ctx.moveTo(x, padT); ctx.lineTo(x, padT + ch2); ctx.stroke();
    var date = new Date(t * 1000);
    var label = (date.getMonth()+1) + '/' + date.getDate();
    ctx.fillText(label, x + 2, h - 5);
  }

  // Draw the curve
  ctx.strokeStyle = '#0f0';
  ctx.lineWidth = 1.5;
  ctx.beginPath();
  var first = true;
  for (var j = 0; j < points.length; j++) {
    var pt = points[j];
    var px = padL + ((pt[0] - tMin) / span) * cw;
    var py = padT + ch2 - (pt[1] / 100) * ch2;
    if (first) { ctx.moveTo(px, py); first = false; }
    else { ctx.lineTo(px, py); }
  }
  ctx.stroke();

  // Draw dry/wet thresholds if available
  if (idx < config.length) {
    var dry = parseInt(config[idx].potDry);
    var wet = parseInt(config[idx].potWet);
    var yDry = padT + ch2 - (dry / 100) * ch2;
    var yWet = padT + ch2 - (wet / 100) * ch2;
    ctx.setLineDash([4, 4]);
    ctx.strokeStyle = '#f80';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(padL, yDry); ctx.lineTo(padL + cw, yDry); ctx.stroke();
    ctx.strokeStyle = '#08f';
    ctx.beginPath(); ctx.moveTo(padL, yWet); ctx.lineTo(padL + cw, yWet); ctx.stroke();
    ctx.setLineDash([]);
  }

  // Hint text
  ctx.fillStyle = '#555';
  ctx.font = '9px sans-serif';
  ctx.fillText('Double-click to close', w - 100, h - 5);
}

function setDry(i, v) {
  v = parseInt(v);
  config[i].potDry = '' + v;
  if (v >= parseInt(config[i].potWet)) {
    config[i].potWet = '' + (v + 1);
    var wetEl = document.getElementById('rwet' + i);
    if (wetEl) wetEl.value = v + 1;
  }
  document.getElementById('ldry' + i).textContent = v + '% dry';
  document.getElementById('lwet' + i).textContent = config[i].potWet + '% wet';
  updateFills();
}

function setWet(i, v) {
  v = parseInt(v);
  config[i].potWet = '' + v;
  if (v <= parseInt(config[i].potDry)) {
    config[i].potDry = '' + (v - 1);
    var dryEl = document.getElementById('rdry' + i);
    if (dryEl) dryEl.value = v - 1;
  }
  document.getElementById('ldry' + i).textContent = config[i].potDry + '% dry';
  document.getElementById('lwet' + i).textContent = v + '% wet';
  updateFills();
}

function setDur(i, v) {
  config[i].watering_duration = '' + parseInt(v);
  document.getElementById('dur' + i).textContent = v;
}

function toggle(i, checked) {
  config[i].potActive = checked ? '1' : '0';
  render();
  updateFills();
  restoreCharts();
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

function updateFills() {
  config.forEach(function(p, i) {
    var fill = document.getElementById('fill' + i);
    if (fill) {
      var dry = parseInt(p.potDry);
      var wet = parseInt(p.potWet);
      fill.style.left = dry + '%';
      fill.style.width = (wet - dry) + '%';
    }
  });
}
updateFills();

function updateCountdown() {
  var el = document.getElementById('countdown');
  if (!pumpStatus.next_measure) { el.textContent = 'Waiting for next measurement...'; return; }
  var now = Date.now() / 1000;
  var diff = Math.max(0, Math.round(pumpStatus.next_measure - now));
  if (diff <= 0) { el.textContent = 'Measuring soon...'; return; }
  var min = Math.floor(diff / 60);
  var sec = diff % 60;
  el.textContent = 'Next measurement in ' + min + 'm ' + (sec < 10 ? '0' : '') + sec + 's';
}
updateCountdown();
setInterval(updateCountdown, 1000);

function updateCpuTemp(temp) {
  var el = document.getElementById('cputemp');
  if (temp === null) { el.textContent = 'CPU: --'; return; }
  el.textContent = 'CPU: ' + temp + '°C';
  el.className = 'cpu-temp' + (temp >= 80 ? ' crit' : temp >= 70 ? ' warn' : '');
}
function updateUptime(ut) {
  var el = document.getElementById('uptime');
  el.textContent = ut ? 'Up: ' + ut : 'Up: --';
}
updateCpuTemp(cpuTemp);
updateUptime(uptime);

setInterval(function() {
  fetch('/humidity').then(function(r) { return r.json(); }).then(function(data) {
    humidity = data.humidity;
    sensors = data.sensors;
    pumpStatus = data.status;
    updateCpuTemp(data.cpu_temp);
    updateUptime(data.uptime);
    render();
    updateFills();
    restoreCharts();
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
            page = page.replace('__CPU_TEMP__', json.dumps(get_cpu_temp()))
            page = page.replace('__UPTIME__', json.dumps(get_uptime()))
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
            self.wfile.write(json.dumps({"humidity": humidity, "sensors": sensors, "status": status, "cpu_temp": get_cpu_temp(), "uptime": get_uptime()}).encode())
        elif self.path.startswith('/history'):
            ch = '0'
            if '?' in self.path:
                params = self.path.split('?')[1]
                for p in params.split('&'):
                    if p.startswith('ch='):
                        ch = p[3:]
            history = load_history()
            points = history.get(ch, [])
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps(points).encode())
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
            return {"watering": [], "testing": False, "next_measure": None}

    def log_message(self, format, *args):
        print("[{}] {}".format(self.client_address[0], format % args))


if __name__ == '__main__':
    # Start history recording thread
    recorder = threading.Thread(target=record_humidity, daemon=True)
    recorder.start()

    server = HTTPServer(('0.0.0.0', PORT), Handler)
    print("Serving on port {}".format(PORT))
    print("Config: {}".format(CONFIG_PATH))
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopped.")
        server.server_close()
