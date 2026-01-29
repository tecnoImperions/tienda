<?php
// bot.php
require_once 'config.php';

/* =============================
   RESPUESTA DEL BOT (AJAX)
============================= */
if (isset($_POST['abot_action'])) {
    $conn = getConnection();
    $action = $_POST['abot_action'];

    // CATEGORÍAS
    if ($action === 'categorias') {
        $res = $conn->query("SELECT descripcion FROM categorias");

        if ($res->num_rows === 0) {
            echo "<div class='abot-msg'>No hay categorías registradas.</div>";
            exit;
        }

        echo "<div class='abot-msg abot-title'>
                <i class='bi bi-tags-fill'></i> Categorías
              </div>";

        while ($r = $res->fetch_assoc()) {
            echo "
            <div class='abot-msg'>
                <i class='bi bi-tag-fill'></i> {$r['descripcion']}
            </div>";
        }
        exit;
    }

    // PRODUCTOS
    if ($action === 'productos') {
        $res = $conn->query("
            SELECT product_name, price 
            FROM productos 
            ORDER BY created_at DESC 
            LIMIT 5
        ");

        if ($res->num_rows === 0) {
            echo "<div class='abot-msg'>No hay productos disponibles.</div>";
            exit;
        }

        echo "<div class='abot-msg abot-title'>
                <i class='bi bi-bicycle'></i> Productos
              </div>";

        while ($r = $res->fetch_assoc()) {
            echo "
            <div class='abot-msg'>
                <i class='bi bi-box-seam'></i>
                <b>{$r['product_name']}</b><br>
                <small>Precio: Bs. {$r['price']}</small>
            </div>";
        }
        exit;
    }

    // AYUDA
    if ($action === 'ayuda') {
        echo "
        <div class='abot-msg abot-title'>
            <i class='bi bi-question-circle-fill'></i> Ayuda
        </div>
        <div class='abot-msg'><i class='bi bi-tags-fill'></i> Ver categorías</div>
        <div class='abot-msg'><i class='bi bi-bicycle'></i> Ver productos</div>
        <div class='abot-msg'><i class='bi bi-cash-stack'></i> Ver precios</div>
        ";
        exit;
    }

    exit;
}
?>

<!-- =============================
     INTERFAZ DEL BOT
============================= -->

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* BOTÓN FLOTANTE */
.abot-btn{
    position:fixed;
    bottom:90px;
    right:20px;
    width:60px;
    height:60px;
    background:#0070ba;
    color:#fff;
    border-radius:14px;
    border:3px solid #e6f0ff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:28px;
    cursor:pointer;
    box-shadow:0 6px 15px rgba(0,0,0,.35);
    z-index:9999;
}

/* CHAT */
.abot-chat{
    position:fixed;
    inset:0;
    background:#f5f7fa;
    display:none;
    flex-direction:column;
    z-index:99999;
}

/* HEADER */
.abot-header{
    background:#0070ba;
    color:#fff;
    padding:14px;
    font-weight:600;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

/* BODY */
.abot-body{
    flex:1;
    padding:12px;
    overflow-y:auto;
}

/* MENSAJES */
.abot-msg{
    background:#fff;
    padding:12px;
    border-radius:14px;
    margin-bottom:10px;
    box-shadow:0 2px 6px rgba(0,0,0,.1);
    font-size:15px;
}

.abot-title{
    font-weight:600;
    background:#e6f0ff;
}

/* OPCIONES */
.abot-options{
    padding:12px;
    border-top:1px solid #ddd;
    background:#fff;
}

.abot-option{
    background:#0070ba;
    color:#fff;
    padding:12px;
    border-radius:14px;
    margin-bottom:10px;
    text-align:center;
    cursor:pointer;
    font-weight:500;
}

.abot-option i{
    margin-right:6px;
}

/* DESKTOP */
@media (min-width:768px){
    .abot-chat{
        inset:auto;
        bottom:160px;
        right:25px;
        width:360px;
        height:560px;
        border-radius:18px;
        box-shadow:0 10px 25px rgba(0,0,0,.4);
    }
}
</style>

<!-- BOTÓN -->
<div class="abot-btn" onclick="toggleAbot()">
    <i class="bi bi-robot"></i>
</div>

<!-- CHAT -->
<div class="abot-chat" id="abotChat">
    <div class="abot-header">
        <span><i class="bi bi-robot"></i> ABOT</span>
        <span style="cursor:pointer" onclick="toggleAbot()">
            <i class="bi bi-x-lg"></i>
        </span>
    </div>

    <div class="abot-body" id="abotBody">
        <div class="abot-msg">
            Selecciona una opción:
        </div>
    </div>

    <div class="abot-options">
        <div class="abot-option" onclick="abotAction('categorias')">
            <i class="bi bi-tags-fill"></i> Categorías
        </div>

        <div class="abot-option" onclick="abotAction('productos')">
            <i class="bi bi-bicycle"></i> Productos
        </div>

        <div class="abot-option" onclick="abotAction('ayuda')">
            <i class="bi bi-question-circle-fill"></i> Ayuda
        </div>
    </div>
</div>

<script>
function toggleAbot(){
    const chat=document.getElementById('abotChat');
    chat.style.display=(chat.style.display==='flex')?'none':'flex';
}

function abotAction(action){
    fetch('bot.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'abot_action='+action
    })
    .then(r=>r.text())
    .then(html=>{
        const body=document.getElementById('abotBody');
        body.innerHTML+=html;
        body.scrollTop=body.scrollHeight;
    });
}
</script>
