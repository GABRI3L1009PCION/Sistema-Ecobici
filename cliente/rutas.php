<?php
// /ecobici/cliente/rutas.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
if (!isset($_SESSION['user'])) { header('Location: /ecobici/login.php'); exit; }

// Estaciones (si existen)
$stations = [];
try {
  $chk = $pdo->query("SHOW TABLES LIKE 'stations'")->fetchColumn();
  if ($chk) {
    $st = $pdo->query("SELECT id,nombre,lat,lng,tipo FROM stations ORDER BY nombre");
    $stations = $st->fetchAll(PDO::FETCH_ASSOC);
  }
} catch(Throwable $e){ $stations = []; }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Rutas personalizadas | EcoBici</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link rel="stylesheet" href="/ecobici/cliente/styles/bootstrap.min.css">
  <!-- Íconos -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- CSS local (tema EcoBici) -->
  <link rel="stylesheet" href="/ecobici/cliente/styles/app.css?v=6"> <!-- versión para evitar caché -->

  <!-- Leaflet & Routing -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">
</head>
<body class="ecobici eco-tight">
  <div class="container py-4">
    <!-- Encabezado -->
<div class="eco-header">
  <div class="eco-header-left">
    <i class="bi bi-map eco-header-icon"></i>
    <h1 class="eco-title">Rutas personalizadas</h1>
  </div>
  <a href="/ecobici/cliente/dashboard.php" class="btn-back">
    <i class="bi bi-arrow-left"></i> Volver
  </a>
</div>


    <div class="row g-2">
      <!-- Panel izquierdo (sticky) -->
      <div class="col-lg-4">
        <div class="card eco-card sticky-panel">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="m-0">Planificador</h5>
              <span class="eco-badge">Puerto Barrios</span>
            </div>

            <div class="mb-2">
              <label class="form-label eco-muted">Tipo de bicicleta</label>
              <select id="tipoBici" class="form-select">
                <option value="tradicional" selected>Tradicional (≈12 km/h)</option>
                <option value="electrica">Eléctrica (≈18 km/h)</option>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label eco-muted">Origen</label>
              <input id="origen" class="form-control" list="lista-estaciones" placeholder="Clic en el mapa o elige una estación">
            </div>

            <div class="mb-2">
              <label class="form-label eco-muted">Destino</label>
              <input id="destino" class="form-control" list="lista-estaciones" placeholder="Clic en el mapa o elige una estación">
            </div>

            <datalist id="lista-estaciones">
              <?php foreach($stations as $s): ?>
                <option value="<?= htmlspecialchars($s['nombre']) ?>"
                        data-lat="<?= htmlspecialchars($s['lat']) ?>"
                        data-lng="<?= htmlspecialchars($s['lng']) ?>"></option>
              <?php endforeach; ?>
            </datalist>

            <div class="d-flex flex-wrap gap-2 mb-3">
              <button id="btnUbicacion" class="btn btn-eco-outline pill">
                <i class="bi bi-geo-alt"></i> Usar mi ubicación
              </button>
              <button id="btnRuta" class="btn btn-eco pill">
                <i class="bi bi-lightning"></i> Trazar ruta
              </button>
              <button id="btnLimpiar" class="btn btn-outline-secondary pill">
                <i class="bi bi-eraser"></i> Limpiar
              </button>
            </div>

            <div class="eco-summary mb-2">
              <div class="eco-muted mb-2">Resumen de la ruta</div>
              <div class="row g-2">
                <div><span>Distancia</span><strong id="resDist">—</strong></div>
                <div><span>Tiempo</span><strong id="resTiempo">—</strong></div>
                <div><span>CO₂ evitado</span><strong id="resCO2">—</strong></div>
              </div>
              <small class="text-muted">* Estimaciones ajustables (velocidad y factor CO₂).</small>
            </div>

            <?php if($stations): ?>
              <hr class="eco-hr">
              <details>
                <summary class="eco-muted" style="cursor:pointer">Estaciones registradas</summary>
                <div class="eco-list vstack gap-2 mt-2">
                  <?php foreach($stations as $s): ?>
                    <div class="eco-list-item">
                      <span class="text-truncate" style="max-width:70%"><?= htmlspecialchars($s['nombre']) ?></span>
                      <span class="eco-badge text-uppercase"><?= htmlspecialchars($s['tipo'] ?? 'selección') ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Mapa -->
      <div class="col-lg-8">
        <div class="card eco-card">
          <div class="card-body p-2">
            <div id="map"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
  <script>
    // ===== Helpers =====
    const toRad = d => d*Math.PI/180;
    const haversineKm = (a,b)=>{
      const R=6371, dLat=toRad(b.lat-a.lat), dLon=toRad(b.lng-a.lng);
      const x=Math.sin(dLat/2)**2 + Math.cos(toRad(a.lat))*Math.cos(toRad(b.lat))*Math.sin(dLon/2)**2;
      return 2*R*Math.asin(Math.sqrt(x));
    };
    const fmtKm = km => km.toFixed(2)+' km';
    const fmtMin = m  => Math.round(m)+' min';
    const estMin = (km,tipo)=> (km/(tipo==='electrica'?18:12))*60;
    const estCO2 = km => (km*120).toFixed(0)+' g'; // 120 g/km

    // ===== Mapa =====
    const pbCenter = [15.727,-88.595];
    const map = L.map('map').setView(pbCenter, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19, attribution:'&copy; OpenStreetMap'}).addTo(map);

    const estaciones = <?php
      echo json_encode(array_map(fn($s)=>[
        'id'=>$s['id'],'nombre'=>$s['nombre'],
        'lat'=>isset($s['lat'])?(float)$s['lat']:null,
        'lng'=>isset($s['lng'])?(float)$s['lng']:null,
        'tipo'=>$s['tipo'] ?? ''
      ], $stations), JSON_UNESCAPED_UNICODE);
    ?>;

    (estaciones||[]).forEach(s=>{
      if(s.lat!=null && s.lng!=null){
        L.marker([s.lat,s.lng]).addTo(map)
          .bindPopup(`<b>${s.nombre}</b><br><small>${s.tipo}</small>`);
      }
    });

    let origen=null, destino=null, markerA=null, markerB=null, control=null;

    map.on('click', e=>{
      if(!origen){
        origen=e.latlng;
        if(markerA) map.removeLayer(markerA);
        markerA=L.marker(origen,{draggable:true}).addTo(map).bindTooltip('Origen').openTooltip();
        markerA.on('dragend',()=>{ origen=markerA.getLatLng(); });
      } else if(!destino){
        destino=e.latlng;
        if(markerB) map.removeLayer(markerB);
        markerB=L.marker(destino,{draggable:true}).addTo(map).bindTooltip('Destino').openTooltip();
        markerB.on('dragend',()=>{ destino=markerB.getLatLng(); });
      }
    });

    function setFromInput(el,isOrigin){
      const val=el.value.trim();
      const opt=[...document.querySelectorAll('#lista-estaciones option')].find(o=>o.value===val);
      if(!opt) return;
      const ll=L.latLng(parseFloat(opt.dataset.lat), parseFloat(opt.dataset.lng));
      if(isOrigin){
        origen=ll; if(markerA) map.removeLayer(markerA);
        markerA=L.marker(origen,{draggable:true}).addTo(map).bindTooltip('Origen').openTooltip();
        markerA.on('dragend',()=>{ origen=markerA.getLatLng(); });
      }else{
        destino=ll; if(markerB) map.removeLayer(markerB);
        markerB=L.marker(destino,{draggable:true}).addTo(map).bindTooltip('Destino').openTooltip();
        markerB.on('dragend',()=>{ destino=markerB.getLatLng(); });
      }
      map.panTo(ll);
    }
    document.getElementById('origen').addEventListener('change',e=>setFromInput(e.target,true));
    document.getElementById('destino').addEventListener('change',e=>setFromInput(e.target,false));

    document.getElementById('btnUbicacion').addEventListener('click',()=>{
      if(!navigator.geolocation) { alert('Geolocalización no soportada'); return; }
      navigator.geolocation.getCurrentPosition(pos=>{
        const ll=L.latLng(pos.coords.latitude,pos.coords.longitude);
        if(!origen){
          origen=ll; if(markerA) map.removeLayer(markerA);
          markerA=L.marker(origen,{draggable:true}).addTo(map).bindTooltip('Origen').openTooltip();
          markerA.on('dragend',()=>{ origen=markerA.getLatLng(); });
        }else{
          destino=ll; if(markerB) map.removeLayer(markerB);
          markerB=L.marker(destino,{draggable:true}).addTo(map).bindTooltip('Destino').openTooltip();
          markerB.on('dragend',()=>{ destino=markerB.getLatLng(); });
        }
        map.setView(ll,15);
      },()=>alert('No fue posible obtener la ubicación'));
    });

    document.getElementById('btnRuta').addEventListener('click',()=>{
      if(!origen || !destino){ alert('Selecciona origen y destino.'); return; }
      if(control){ map.removeControl(control); control=null; }

      control = L.Routing.control({
        waypoints: [origen, destino],
        lineOptions: { addWaypoints:false },
        router: L.Routing.osrmv1({ serviceUrl:'https://router.project-osrm.org/route/v1' }),
        draggableWaypoints:false, show:false
      })
      .on('routesfound', e=>{
        const km=e.routes[0].summary.totalDistance/1000;
        const minutos=estMin(km, document.getElementById('tipoBici').value);
        document.getElementById('resDist').textContent=fmtKm(km);
        document.getElementById('resTiempo').textContent=fmtMin(minutos);
        document.getElementById('resCO2').textContent=estCO2(km);
      })
      .on('routingerror', ()=>{
        const km=haversineKm({lat:origen.lat,lng:origen.lng},{lat:destino.lat,lng:destino.lng});
        const minutos=estMin(km, document.getElementById('tipoBici').value);
        document.getElementById('resDist').textContent=fmtKm(km);
        document.getElementById('resTiempo').textContent=fmtMin(minutos);
        document.getElementById('resCO2').textContent=estCO2(km);
        const poly=L.polyline([origen,destino],{weight:5}).addTo(map);
        setTimeout(()=>map.fitBounds(poly.getBounds(),{padding:[30,30]}),50);
      })
      .addTo(map);
    });

    document.getElementById('btnLimpiar').addEventListener('click',()=>{
      if(markerA){ map.removeLayer(markerA); markerA=null; }
      if(markerB){ map.removeLayer(markerB); markerB=null; }
      origen=destino=null;
      document.getElementById('origen').value='';
      document.getElementById('destino').value='';
      if(control){ map.removeControl(control); control=null; }
      document.getElementById('resDist').textContent='—';
      document.getElementById('resTiempo').textContent='—';
      document.getElementById('resCO2').textContent='—';
      map.setView(<?php echo json_encode($stations ? [ (float)$stations[0]['lat'], (float)$stations[0]['lng'] ] : [15.727,-88.595]); ?>, 13);
    });
  </script>
</body>
</html>
