<?php
/**
 * Template Name: AI Tutor Page
 */
if ( ! is_user_logged_in() ) {
    wp_redirect( add_query_arg(['locked'=>'1','redirect_to'=>urlencode(get_permalink())], home_url('/login')) );
    exit;
}
get_header();
$user = wp_get_current_user();
$name = esc_html($user->display_name);
?>

<div style="background:linear-gradient(135deg,#050b1a 0%,#0a1628 100%);min-height:100vh;padding:30px 20px;">

  <!-- Back Button -->
  <a href="<?php echo esc_url(home_url('/')); ?>"
     style="display:inline-flex;align-items:center;gap:8px;color:#88aaff;text-decoration:none;font-size:.9rem;margin-bottom:25px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:50px;padding:8px 18px;transition:.3s"
     onmouseover="this.style.borderColor='#00aaff';this.style.color='#00aaff'"
     onmouseout="this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='#88aaff'">
    ← Back to Home
  </a>

  <!-- Header -->
  <div style="text-align:center;margin-bottom:30px;">
    <h1 style="color:white;font-size:2.5rem;margin:0 0 10px 0;">🤖 AI Biology Tutor</h1>
    <p style="color:#88aaff;margin-bottom:5px;">Ask any question about the human body!</p>
    <span style="background:rgba(0,170,255,0.1);border:1px solid rgba(0,170,255,0.3);color:#00aaff;border-radius:50px;padding:4px 14px;font-size:.8rem;">👋 Hello, <?php echo $name; ?>!</span>
  </div>

  <!-- Chat Window -->
  <div style="max-width:800px;margin:0 auto;">
    <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.4);">

      <!-- Messages Area -->
      <div id="chatArea" style="height:420px;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:15px;scroll-behavior:smooth;">
        <!-- Welcome message -->
        <div style="display:flex;gap:12px;align-items:flex-start;">
          <div style="width:38px;height:38px;border-radius:50%;background:rgba(0,170,255,0.2);border:1px solid rgba(0,170,255,0.3);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">🤖</div>
          <div style="background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:16px;padding:14px 18px;max-width:80%;font-size:.92rem;line-height:1.6;color:white;">
            <p style="margin:0 0 8px">👋 Hello <strong style="color:#00ffcc"><?php echo $name; ?></strong>! I'm your AI Biology Tutor.</p>
            <p style="margin:0 0 10px;color:#88aaff;">Ask me anything about the human body. Examples:</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
              <button onclick="quickAsk('What does the heart do?')" style="background:rgba(0,170,255,0.12);border:1px solid rgba(0,170,255,0.25);color:#00aaff;border-radius:50px;padding:5px 12px;font-size:.78rem;cursor:pointer;font-family:inherit;">❤️ What does the heart do?</button>
              <button onclick="quickAsk('Tell me about the brain')" style="background:rgba(0,170,255,0.12);border:1px solid rgba(0,170,255,0.25);color:#00aaff;border-radius:50px;padding:5px 12px;font-size:.78rem;cursor:pointer;font-family:inherit;">🧠 Tell me about the brain</button>
              <button onclick="quickAsk('How do lungs work?')" style="background:rgba(0,170,255,0.12);border:1px solid rgba(0,170,255,0.25);color:#00aaff;border-radius:50px;padding:5px 12px;font-size:.78rem;cursor:pointer;font-family:inherit;">🫁 How do lungs work?</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Typing indicator -->
      <div id="typingDiv" style="display:none;padding:5px 20px 8px;color:#88aaff;font-size:.82rem;">🤖 Thinking<span id="dots">...</span></div>

      <!-- Input Area -->
      <div style="border-top:1px solid rgba(255,255,255,0.08);padding:15px;display:flex;gap:10px;align-items:center;background:rgba(0,0,0,0.2);">
        <input type="text" id="userInput"
          placeholder="Type your biology question here..."
          style="flex:1;padding:13px 18px;border:1px solid rgba(255,255,255,0.1);border-radius:50px;background:rgba(255,255,255,0.07);color:white;font-size:.95rem;outline:none;font-family:inherit;transition:.3s;"
          onfocus="this.style.borderColor='#00aaff'"
          onblur="this.style.borderColor='rgba(255,255,255,0.1)'"
          onkeydown="if(event.key==='Enter')sendMsg()">
        <button onclick="sendMsg()" id="sendBtn"
          style="width:46px;height:46px;border-radius:50%;background:#00aaff;border:none;color:white;font-size:1.1rem;cursor:pointer;transition:.3s;flex-shrink:0;"
          onmouseover="this.style.background='#0088cc'"
          onmouseout="this.style.background='#00aaff'">➤</button>
        <button onclick="clearChat()"
          style="padding:11px 18px;border-radius:50px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:#88aaff;font-size:.82rem;cursor:pointer;font-family:inherit;transition:.3s;"
          onmouseover="this.style.background='rgba(255,255,255,0.1)'"
          onmouseout="this.style.background='rgba(255,255,255,0.06)'">Clear</button>
      </div>
    </div>

    <!-- Quick Topics -->
    <div style="margin-top:25px;">
      <p style="color:#88aaff;font-size:.8rem;margin-bottom:12px;text-transform:uppercase;letter-spacing:.06em;">Quick Questions:</p>
      <div style="display:flex;flex-wrap:wrap;gap:10px;">
        <?php
        $topics = [
          ['❤️','Heart','What does the heart do and how does it pump blood?'],
          ['🧠','Brain','Explain how the brain controls the body'],
          ['🫁','Lungs','How do the lungs work and exchange oxygen?'],
          ['🦴','Bones','How many bones are in the human body?'],
          ['🔴','Liver','What are the main functions of the liver?'],
          ['🫘','Kidneys','How do the kidneys filter blood?'],
          ['🥘','Stomach','How does the stomach digest food?'],
          ['💪','Muscles','What are the three types of muscle tissue?'],
        ];
        foreach($topics as $t):?>
        <button onclick="quickAsk('<?php echo esc_js($t[2]); ?>')"
          style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);color:white;border-radius:50px;padding:9px 18px;font-size:.85rem;cursor:pointer;font-family:inherit;transition:.3s;"
          onmouseover="this.style.background='rgba(0,170,255,0.12)';this.style.borderColor='rgba(0,170,255,0.3)';this.style.color='#00aaff'"
          onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='white'">
          <?php echo $t[0].' '.$t[1]; ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
var history = [];
var userInitial = '<?php echo esc_js(strtoupper(substr($user->display_name,0,1))); ?>';

// Dot animation
setInterval(function(){
  var d = document.getElementById('dots');
  if(d){ d.textContent = d.textContent.length >= 3 ? '.' : d.textContent + '.'; }
}, 500);

function addMsg(role, text) {
  var area = document.getElementById('chatArea');
  var isAI = role === 'assistant';
  var d = document.createElement('div');
  d.style.cssText = 'display:flex;gap:12px;align-items:flex-start;' + (isAI ? '' : 'flex-direction:row-reverse;');

  var avatar = document.createElement('div');
  avatar.style.cssText = 'width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;' +
    (isAI ? 'background:rgba(0,170,255,0.2);border:1px solid rgba(0,170,255,0.3);' : 'background:rgba(0,255,204,0.15);border:1px solid rgba(0,255,204,0.25);font-weight:800;font-size:.85rem;color:#00ffcc;');
  avatar.textContent = isAI ? '🤖' : userInitial;

  var bubble = document.createElement('div');
  bubble.style.cssText = 'max-width:80%;padding:13px 17px;border-radius:16px;font-size:.91rem;line-height:1.65;' +
    (isAI ? 'background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);color:white;' :
             'background:linear-gradient(135deg,rgba(0,100,200,.35),rgba(0,170,255,.2));border:1px solid rgba(0,170,255,.3);color:white;');

  // Format AI response: bold, bullets
  if (isAI) {
    var html = text
      .replace(/\*\*(.*?)\*\*/g, '<strong style="color:#00ffcc">$1</strong>')
      .split('\n').map(function(line) {
        if (/^[-•*]\s/.test(line)) return '<li style="margin:.2rem 0">' + line.replace(/^[-•*]\s/,'') + '</li>';
        return line ? '<p style="margin:.3rem 0">' + line + '</p>' : '';
      }).join('').replace(/(<li[^>]*>.*<\/li>)/gs, '<ul style="padding-left:1.2rem;margin:.4rem 0">$1</ul>');
    bubble.innerHTML = html || '<p>' + text + '</p>';
  } else {
    bubble.textContent = text;
  }

  d.appendChild(avatar); d.appendChild(bubble);
  area.appendChild(d);
  area.scrollTop = area.scrollHeight;
}

function setTyping(on) {
  document.getElementById('typingDiv').style.display = on ? 'block' : 'none';
  document.getElementById('sendBtn').disabled = on;
  document.getElementById('sendBtn').style.opacity = on ? '.4' : '1';
}

function quickAsk(text) {
  document.getElementById('userInput').value = text;
  sendMsg();
}

async function sendMsg() {
  var inp = document.getElementById('userInput');
  var text = inp.value.trim();
  if (!text) return;
  inp.value = '';
  addMsg('user', text);
  history.push({role:'user', content:text});
  setTyping(true);
  try {
    var res = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        model: 'claude-sonnet-4-6',
        max_tokens: 1000,
        system: 'You are BioTutor, an expert human biology teacher. You ONLY answer questions about human biology, anatomy, physiology, body systems, organs, cells, and health. Use **bold** for key terms and bullet points for lists. Keep answers clear and educational. If a question is not about biology, say: "I can only help with human biology topics! Try asking about organs, body systems, or anatomy."',
        messages: history
      })
    });
    var data = await res.json();
    var reply = (data.content && data.content[0] && data.content[0].text) ? data.content[0].text : '⚠️ Could not get a response. Please try again.';
    history.push({role:'assistant', content:reply});
    addMsg('assistant', reply);
  } catch(e) {
    addMsg('assistant', '⚠️ Connection error. Please check your internet and try again.');
  }
  setTyping(false);
}

function clearChat() {
  history = [];
  var area = document.getElementById('chatArea');
  // Keep only the welcome message (first child)
  while (area.children.length > 1) area.removeChild(area.lastChild);
}
</script>

<?php get_footer(); ?>
