<?php
// views/bot.php
require_once '../includes/config.php';

/* =============================
   RESPUESTA DEL BOT (AJAX)
============================= */
if (isset($_POST['abot_action'])) {
    $conn = getConnection();
    $action = $_POST['abot_action'];
    $query = $_POST['query'] ?? '';
    usleep(400000);

    header('Content-Type: application/json');
    $messages = [];

    if ($action === 'buscar_producto' && !empty($query)) {
        $search = '%' . $query . '%';
        $stmt = $conn->prepare("
            SELECT product_name, price, foto
            FROM productos
            WHERE product_name LIKE ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $messages[] = ["abot", "No encontré productos con <b>$query</b>."];
        } else {
            $messages[] = ["abot", "Encontré estos productos:"];
            while ($r = $res->fetch_assoc()) {
                $msg = "<b>{$r['product_name']}</b><br>Bs. " . number_format($r['price'], 2);
                if ($r['foto']) {
                    $msg .= "<br><img src='{$r['foto']}' style='max-width:140px;margin-top:8px;border-radius:10px'>";
                }
                $messages[] = ["abot", $msg];
            }
        }
    } elseif ($action === 'categorias') {
        $res = $conn->query("SELECT descripcion FROM categorias ORDER BY descripcion ASC");
        $messages[] = ["abot", "Categorías disponibles:"];
        while ($r = $res->fetch_assoc()) {
            $messages[] = ["abot", "• {$r['descripcion']}"];
        }
    } elseif ($action === 'productos') {
        $res = $conn->query("
            SELECT product_name, price, foto
            FROM productos
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $messages[] = ["abot", "Productos recientes:"];
        while ($r = $res->fetch_assoc()) {
            $msg = "<b>{$r['product_name']}</b><br>Bs. " . number_format($r['price'], 2);
            if ($r['foto']) {
                $msg .= "<br><img src='{$r['foto']}' style='max-width:140px;margin-top:8px;border-radius:10px'>";
            }
            $messages[] = ["abot", $msg];
        }
    } elseif ($action === 'como_comprar') {
        $messages = [
            ["abot", "<b>Guía de compra</b>"],
            ["abot", "1️⃣ Explora el catálogo"],
            ["abot", "2️⃣ Agrega al carrito"],
            ["abot", "3️⃣ Paga con QR"],
            ["abot", "4️⃣ Recoge en sucursal"]
        ];
    } else {
        $messages[] = ["abot", "Escribe lo que deseas buscar 😊"];
    }

    echo json_encode($messages);
    exit;
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* =============================
   BOTÓN FLOTANTE (MEJORADO)
============================= */
.abot-btn{
    position:fixed;
    bottom:130px;          /* MÁS ARRIBA */
    right:20px;
    width:64px;            /* MÁS GRANDE */
    height:64px;
    background:linear-gradient(135deg,#0d6efd,#0b5ed7);
    color:#fff;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:30px;
    cursor:pointer;
    z-index:9999;
    box-shadow:0 10px 28px rgba(13,110,253,.4);
    transition:all .25s ease;
}
.abot-btn:hover{
    transform:translateY(-4px) scale(1.05);
}

/* =============================
   OVERLAY
============================= */
.abot-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    display:none;
    z-index:10000
}
.abot-overlay.active{display:block}

/* =============================
   CHAT
============================= */
.abot-chat{
    position:fixed;
    top:0;
    right:-420px;
    width:420px;
    height:100vh;
    background:#fff;
    z-index:10001;
    display:flex;
    flex-direction:column;
    transition:right .35s ease;
}
.abot-chat.active{right:0}

/* HEADER */
.abot-header{
    background:linear-gradient(135deg,#0d6efd,#0b5ed7);
    color:#fff;
    padding:1rem;
    display:flex;
    justify-content:space-between;
    align-items:center
}

/* BODY */
.abot-body{
    flex:1;
    padding:1rem;
    overflow-y:auto;
    background:#f5f9ff
}

/* MENSAJES */
.abot-msg{
    max-width:85%;
    padding:.8rem 1rem;
    border-radius:16px;
    margin-bottom:.6rem;
    font-size:.9rem;
}
.abot-msg.abot{
    background:#fff;
    align-self:flex-start
}
.abot-msg.user{
    background:#0d6efd;
    color:#fff;
    align-self:flex-end
}
.abot-msg.thinking{
    background:#e7f0ff;
    font-style:italic
}

/* INPUT */
.abot-input-area{
    padding:.75rem;
    display:flex;
    gap:.5rem;
    border-top:1px solid #e5e7eb
}
.abot-input{
    flex:1;
    padding:.75rem 1rem;
    border-radius:14px;
    border:1px solid #ddd
}
.abot-send{
    width:46px;
    height:46px;
    border-radius:50%;
    border:none;
    background:#0d6efd;
    color:#fff
}

/* OPCIONES */
.abot-options{
    padding:.75rem;
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:.5rem;
    border-top:1px solid #e5e7eb
}
.abot-option{
    background:#f0f7ff;
    padding:.75rem;
    border-radius:12px;
    text-align:center;
    cursor:pointer;
    font-size:.85rem;
}

/* =============================
   MÓVIL PERFECTO
============================= */
@media(max-width:767px){
    .abot-btn{
        bottom:180px;      /* BIEN ARRIBA EN CELULAR */
        right:16px;
        width:68px;
        height:68px;
        font-size:32px;
    }
    .abot-chat{
        width:100%;
        right:-100%;
        height:100dvh;
    }
    .abot-chat.active{right:0}
}
</style>

<!-- BOTÓN -->
<div class="abot-btn" onclick="toggleAbot()">
    <i class="bi bi-robot"></i>
</div>

<div class="abot-overlay" id="abotOverlay" onclick="toggleAbot()"></div>

<div class="abot-chat" id="abotChat">
    <div class="abot-header">
        <strong>🤖 ABOT</strong>
        <span onclick="toggleAbot()" style="cursor:pointer">✖</span>
    </div>

    <div class="abot-body" id="abotBody">
        <div class="abot-msg abot">
            Hola 👋 soy <b>ABOT</b><br>
            ¿En qué te ayudo?
        </div>
    </div>

    <div class="abot-input-area">
        <input class="abot-input" id="abotInput" placeholder="Escribe aquí..."
               onkeypress="if(event.key==='Enter')sendMessage()">
        <button class="abot-send" onclick="sendMessage()">➤</button>
    </div>

    <div class="abot-options">
        <div class="abot-option" onclick="abotAction('productos')">🚲 Productos</div>
        <div class="abot-option" onclick="abotAction('categorias')">🏷 Categorías</div>
        <div class="abot-option" onclick="abotAction('como_comprar')">🛒 Comprar</div>
        <div class="abot-option" onclick="abotAction('ayuda')">❓ Ayuda</div>
    </div>
</div>

<script>
function toggleAbot(){
    document.getElementById('abotChat').classList.toggle('active');
    document.getElementById('abotOverlay').classList.toggle('active');
}

function sendMessage(){
    const input=document.getElementById('abotInput');
    if(!input.value.trim())return;
    const body=document.getElementById('abotBody');
    body.innerHTML+=`<div class="abot-msg user">${input.value}</div>`;
    abotAction('buscar_producto',input.value);
    input.value='';
}

function abotAction(action,query=''){
    const body=document.getElementById('abotBody');
    body.innerHTML+=`<div class="abot-msg thinking">Escribiendo...</div>`;
    fetch('bot.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({abot_action:action,query})
    }).then(r=>r.json()).then(msgs=>{
        body.querySelector('.thinking')?.remove();
        msgs.forEach(m=>{
            body.innerHTML+=`<div class="abot-msg ${m[0]}">${m[1]}</div>`;
            body.scrollTop=body.scrollHeight;
        });
    });
}
</script>
