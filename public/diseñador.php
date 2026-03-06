<?php
// diseñador.php: Plano interactivo de gimnasio tipo "Habbo"
require_once __DIR__ . '/../app/auth.php';
require_login();
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/navbar.php';
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Diseñador de Gimnasio</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { background: #222; color: #fff; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; }
    .designer-container { max-width: 1200px; margin: 0 auto; padding: 24px; }
    h1 { text-align: center; margin-bottom: 18px; color: #111 !important; }
    .controls { background: #181818; padding: 16px; border-radius: 8px; margin-bottom: 18px; display: flex; flex-wrap: wrap; gap: 18px; align-items: center; justify-content: center; }
    .controls label { margin-right: 8px; }
    #gym-canvas { background: #444; display: block; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 12px #0008; }
    .machine-list { margin-top: 18px; display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
    .machine-item { background: #333; border: 2px solid #666; border-radius: 6px; padding: 8px 12px; cursor: grab; color: #fff; font-size: 1em; }
    .machine-item.selected, .machine-item:hover { border-color: #ffb300; background: #222; }
    .legend { margin-top: 18px; text-align: center; color: #bbb; font-size: 0.98em; }
  </style>
</head>
<body>
<script>
// Forzar fondo y color de texto oscuros en el body, sobrescribiendo Bootstrap
document.addEventListener('DOMContentLoaded', function() {
  document.body.style.background = '#222';
  document.body.style.color = '#fff';
});
</script>
<div class="designer-container">
  <h1>Diseñador de Gimnasio</h1>
  <div class="controls">
    <label>Metros cuadrados del gym:
      <input type="number" id="gym-m2" value="100" min="10" max="1000" style="width:70px;">
    </label>
    <label>Ancho (m): <input type="number" id="gym-width" value="10" min="2" max="50" style="width:60px;"></label>
    <label>Largo (m): <input type="number" id="gym-height" value="10" min="2" max="50" style="width:60px;"></label>
    <button onclick="resetGym()">Limpiar plano</button>
  </div>
  <canvas id="gym-canvas" width="800" height="600"></canvas>
  <div class="machine-list" id="machine-list"></div>
  <div class="legend">
    Arrastra una máquina al plano. Haz click para seleccionarla y moverla. <br>
    Puedes cambiar el tamaño del gym y ver cómo se ajusta el plano.
  </div>
</div>

<!-- Cargar máquinas reales desde la base de datos -->
<?php
$pdo = null;
require_once '../app/db.php';
$pdo = db();
$stmt = $pdo->query("SELECT id, nombre, metros_cuadrados FROM products WHERE tipo = 'PT'");
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
$colorList = ['#e53935','#43a047','#1e88e5','#fbc02d','#8e24aa','#00897b','#6d4c41','#757575','#90caf9','#a5d6a7','#ffe082','#f48fb1','#ce93d8','#ffab91','#b0bec5'];
foreach($machines as $i=>&$m) {
  $m['color'] = $colorList[$i % count($colorList)];
}
?>
<script>
const MACHINES = <?php echo json_encode($machines, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;

let gymWidth = 10, gymHeight = 10, gymM2 = 100;
let placedMachines = [];
let selectedMachine = null;
let dragging = false;
let dragOffset = {x:0, y:0};

const canvas = document.getElementById('gym-canvas');
const ctx = canvas.getContext('2d');

function m2ToPx(m) {
  // Calcula el tamaño de 1m en px según el tamaño del canvas y el gym
  return Math.min(canvas.width / gymWidth, canvas.height / gymHeight);
}

function drawGym() {
  ctx.clearRect(0,0,canvas.width,canvas.height);
  // Fondo
  ctx.fillStyle = '#222';
  ctx.fillRect(0,0,canvas.width,canvas.height);
  // Cuadricula
  let px = m2ToPx(1);
  ctx.strokeStyle = '#555';
  for(let i=0;i<=gymWidth;i++) {
    ctx.beginPath();
    ctx.moveTo(i*px,0); ctx.lineTo(i*px,canvas.height);
    ctx.stroke();
  }
  for(let j=0;j<=gymHeight;j++) {
    ctx.beginPath();
    ctx.moveTo(0,j*px); ctx.lineTo(canvas.width,j*px);
    ctx.stroke();
  }
  // Máquinas
  placedMachines.forEach((m,i)=>{
    ctx.save();
    ctx.globalAlpha = selectedMachine===i ? 0.85 : 1;
    ctx.fillStyle = m.color;
    ctx.strokeStyle = selectedMachine===i ? '#fff' : '#111';
    ctx.lineWidth = selectedMachine===i ? 3 : 1.5;
    ctx.beginPath();
    ctx.rect(m.x*px, m.y*px, m.w*px, m.h*px);
    ctx.fill();
    ctx.stroke();
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 14px Segoe UI,Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(m.name, m.x*px + m.w*px/2, m.y*px + m.h*px/2);
    ctx.restore();
  });
}

function updateGymSize() {
  gymWidth = parseInt(document.getElementById('gym-width').value)||10;
  gymHeight = parseInt(document.getElementById('gym-height').value)||10;
  gymM2 = gymWidth*gymHeight;
  document.getElementById('gym-m2').value = gymM2;
  drawGym();
}

document.getElementById('gym-width').addEventListener('input', updateGymSize);
document.getElementById('gym-height').addEventListener('input', updateGymSize);
document.getElementById('gym-m2').addEventListener('input', function(){
  let m2 = parseInt(this.value)||100;
  let side = Math.round(Math.sqrt(m2));
  document.getElementById('gym-width').value = side;
  document.getElementById('gym-height').value = side;
  updateGymSize();
});

function resetGym() {
  placedMachines = [];
  selectedMachine = null;
  drawGym();
}

function renderMachineList() {
  const list = document.getElementById('machine-list');
  list.innerHTML = '';
  MACHINES.forEach((m,idx)=>{
    const el = document.createElement('div');
    el.className = 'machine-item';
    // Usar el campo correcto de la BD
    el.textContent = (m.nombre || m.name) + ' ('+(m.metros_cuadrados || m.m2)+' m²)';
    el.style.borderColor = m.color;
    el.style.background = m.color;
    el.onclick = ()=>{
      // Al hacer click, agregar al plano
      let px = m2ToPx(1);
      let size = Math.sqrt(m.metros_cuadrados || m.m2);
      placedMachines.push({
        name: m.nombre || m.name,
        color: m.color,
        w: size,
        h: size,
        x: 1,
        y: 1
      });
      drawGym();
    };
    list.appendChild(el);
  });
}

canvas.addEventListener('mousedown', function(e){
  let px = m2ToPx(1);
  let rect = canvas.getBoundingClientRect();
  let mx = (e.clientX-rect.left)/px;
  let my = (e.clientY-rect.top)/px;
  selectedMachine = null;
  for(let i=placedMachines.length-1;i>=0;i--) {
    let m = placedMachines[i];
    if(mx>=m.x && mx<=m.x+m.w && my>=m.y && my<=m.y+m.h) {
      selectedMachine = i;
      dragOffset.x = mx-m.x;
      dragOffset.y = my-m.y;
      dragging = true;
      break;
    }
  }
  drawGym();
});

canvas.addEventListener('mousemove', function(e){
  if(dragging && selectedMachine!==null) {
    let px = m2ToPx(1);
    let rect = canvas.getBoundingClientRect();
    let mx = (e.clientX-rect.left)/px;
    let my = (e.clientY-rect.top)/px;
    let m = placedMachines[selectedMachine];
    m.x = Math.max(0, Math.min(gymWidth-m.w, mx-dragOffset.x));
    m.y = Math.max(0, Math.min(gymHeight-m.h, my-dragOffset.y));
    drawGym();
  }
});

canvas.addEventListener('mouseup', function(){ dragging = false; });
canvas.addEventListener('mouseleave', function(){ dragging = false; });

renderMachineList();
drawGym();
</script>
</body>
  <style>
    html, body, .designer-container, .controls, .legend, h1, label, input, button, .machine-item, .navbar, .navbar * {
      color: #fff !important;
      background: transparent;
      border-color: #fff !important;
    }
    body {
      background: #222 !important;
      font-family: 'Segoe UI', Arial, sans-serif;
      margin: 0;
    }
    .designer-container { max-width: 1200px; margin: 0 auto; padding: 24px; }
    h1 { text-align: center; margin-bottom: 18px; }
    .controls { background: #181818; padding: 16px; border-radius: 8px; margin-bottom: 18px; display: flex; flex-wrap: wrap; gap: 18px; align-items: center; justify-content: center; }
    .controls label { margin-right: 8px; }
    #gym-canvas { background: #444; display: block; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 12px #0008; }
    .machine-list { margin-top: 18px; display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
    .machine-item { background: #333; border: 2px solid #666; border-radius: 6px; padding: 8px 12px; cursor: grab; color: #fff !important; font-size: 1em; }
    .machine-item.selected, .machine-item:hover { border-color: #ffb300; background: #222; }
    .legend { margin-top: 18px; text-align: center; color: #bbb !important; font-size: 0.98em; }
    input, button {
      background: #222 !important;
      color: #fff !important;
      border: 1px solid #fff !important;
    }
    .navbar, .navbar * {
      color: #fff !important;
    }
  </style>
