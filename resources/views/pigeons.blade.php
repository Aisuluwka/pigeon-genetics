<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Pigeon Genetics — визуализация</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{ --bg:#fafafa; --card:#fff; --text:#111827; --muted:#6b7280; --line:#e5e7eb; --accent:#111827; --pigeon-size:92px; }
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--text);margin:0}
    .wrap{max-width:1200px;margin:32px auto;padding:0 16px}
    h1{margin:0 0 12px 0}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px;box-shadow:0 2px 6px rgba(0,0,0,.04)}
    label{font-weight:600;margin:8px 0 6px;display:block}
    input[type=text]{width:100%;padding:12px;border:1px solid var(--line);border-radius:12px;font-size:16px}
    select{padding:10px;border:1px solid var(--line);border-radius:10px}
    .row{display:flex;gap:12px;flex-wrap:wrap}
    button,.btn{background:var(--accent);color:#fff;border:0;border-radius:12px;padding:10px 14px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;gap:8px;align-items:center}
    .muted{color:var(--muted)}
    .errors{color:#b91c1c;margin:8px 0}
    .punnett{border-collapse:collapse;width:100%;margin-top:8px}
    .punnett th,.punnett td{border:1px solid var(--line);text-align:center;padding:8px;vertical-align:top}
    .tag{display:inline-block;background:#eef2ff;color:#3730a3;border-radius:999px;padding:4px 10px;font-size:12px}
    .tips{font-size:14px;color:var(--muted)}
    .k{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:14px}
    .center{display:flex;justify-content:center;align-items:center}
    .legend{display:flex;gap:12px;flex-wrap:wrap}
    .chip{display:inline-flex;gap:8px;align-items:center;border:1px solid var(--line);border-radius:999px;padding:6px 10px;font-size:14px}
    .dot{width:14px;height:14px;border-radius:50%}
    .scroll{overflow:auto}

    /* ВИЗУАЛ ГОЛУБЕЙ — обновлённый вид */
    .pigeon{ width:var(--pigeon-size); height:var(--pigeon-size); margin:0 auto }
    .pigeon svg{width:100%;height:100%}
    .pheno-dark        { --body:#2b3442; --wing:#111827; --outline:#0b1220; }
    .pheno-dark-dilute { --body:#9aa0a8; --wing:#6b7280; --outline:#3f4753; }
    .pheno-blue        { --body:#536f9e; --wing:#2f517f; --outline:#2a4368; }
    .pheno-blue-dilute { --body:#c6d5ec; --wing:#9fb5d7; --outline:#6e87aa; }

    .body{fill:var(--body)}
    .wing{fill:var(--wing)}
    .beak{fill:#f5a25b}
    .eye{fill:#111}
    .leg{fill:#cc6e4a}
    .outline{fill:none;stroke:var(--outline);stroke-width:1.4}
    /* Пегость P_: белые пятна */
    .patch{fill:#fff;opacity:.95;display:none}
    .piebald-on .patch{display:block}

    .label{font-size:12px;color:var(--muted)}

    /* Подсветка 9:3:3:1 */
    .highlight-9331{ box-shadow: inset 0 0 0 3px rgba(16,185,129,.7); border-radius:10px; }

    @media print {
      .row, select, input, button, .legend, .tips { display:none !important; }
      .card { box-shadow:none; border:1px solid #ddd; }
      body { background:#fff; }
    }
  </style>
</head>
<body>
<div class="wrap">
  <h1>Генетика голубей: визуализация 2–3 локусов (S/s, D/d, P/p)</h1>
  <p class="tips">
    <b>S/s</b> — Spread (S_ тёмный), <b>D/d</b> — Dilute (dd осветляет), <b>P/p</b> — Piebald (P_ белые пятна).
    Это учебная менделевская модель (независимые локусы, полное доминирование S и P).
  </p>

  <div class="grid2">
    <div class="card">
      <form method="post" class="row" style="flex-direction:column">
        @csrf
        <label>Режим</label>
        <div class="row">
          <select name="mode" onchange="onModeChange(this.value)">
            <option value="2" {{ $mode==2?'selected':'' }}>2 локуса (S/s, D/d)</option>
            <option value="3" {{ $mode==3?'selected':'' }}>3 локуса (S/s, D/d, P/p)</option>
          </select>
          <span class="muted">4×4 или 8×8</span>
        </div>

        <label>Масштаб иконок</label>
        <div class="row">
          <input type="range" min="60" max="110" value="92" oninput="setScale(this.value)">
          <span class="muted" id="scaleLabel">92 px</span>
        </div>

        <label>Родитель 1</label>
        <input type="text" name="parent1" value="{{ $parent1 }}" placeholder="{{ $mode==2?'SsDd':'SsDdPp' }}" maxlength="{{ $mode==2?4:6 }}" required>

        <label>Родитель 2</label>
        <input type="text" name="parent2" value="{{ $parent2 }}" placeholder="{{ $mode==2?'SsDd':'SsDdPp' }}" maxlength="{{ $mode==2?4:6 }}" required>

        @if ($errs)
          <div class="errors">@foreach ($errs as $e) <div>• {{ $e }}</div> @endforeach</div>
        @endif

        <div class="row">
          <button type="submit">Рассчитать Пеннета</button>
          @if($mode==2)
            <a class="btn" href="#" onclick="fillScenario('SsDd','SsDd');return false;">Сценарий 1</a>
            <a class="btn" href="#" onclick="fillScenario('SSdd','ssDD');return false;">Сценарий 2</a>
            <a class="btn" href="#" onclick="fillScenario('SSDD','ssdd');return false;">Сценарий 3</a>
          @else
            <a class="btn" href="#" onclick="fillScenario('SsDdPp','SsDdPp');return false;">Сценарий 1 (3-лок.)</a>
            <a class="btn" href="#" onclick="fillScenario('SSddPp','ssDDpp');return false;">Сценарий 2 (3-лок.)</a>
          @endif
        </div>
      </form>
    </div>

    <div class="card">
      <div><span class="tag">Легенда фенотипов</span></div>
      <div class="legend" style="margin-top:10px">
        <div class="chip"><span class="dot pheno-dark" style="background:var(--body)"></span> Dark (Spread)</div>
        <div class="chip"><span class="dot pheno-dark-dilute" style="background:var(--body)"></span> Dark + Dilute</div>
        <div class="chip"><span class="dot pheno-blue" style="background:var(--body)"></span> Non-spread</div>
        <div class="chip"><span class="dot pheno-blue-dilute" style="background:var(--body)"></span> Non-spread + Dilute</div>
        <div class="chip"><span class="dot" style="background:#fff;border:1px solid var(--line)"></span> + Piebald (белые пятна при P_)</div>
      </div>
    </div>
  </div>

  @if ($computed)
    <div class="card" style="margin-top:16px">
      <h3>Квадрат Пеннета ({{ $parent1 }} × {{ $parent2 }})</h3>

      <div class="row" style="justify-content:flex-end;margin-bottom:8px">
        <button type="button" class="btn" onclick="savePng()">Скачать как PNG</button>
        <button type="button" class="btn" onclick="window.print()">Печать / PDF</button>
      </div>

      <div class="scroll">
      <table class="punnett">
        <tr>
          <th></th>
          @foreach($computed['gametes2'] as $g2)
            <th class="k">{{ $g2 }}</th>
          @endforeach
        </tr>
        @for ($i=0; $i<count($computed['gametes1']); $i++)
          <tr>
            <th class="k">{{ $computed['gametes1'][$i] }}</th>
            @for ($j=0; $j<count($computed['gametes2']); $j++)
              @php
                $cell = $computed['grid'][$i][$j]; $child = $cell['child'];
                $isClassic = (count($computed['locusOrder'])===2
                              && strcasecmp($parent1,'SsDd')===0
                              && strcasecmp($parent2,'SsDd')===0);
                $SD   = isset($child['S'],$child['D']) && ($child['S']!=='ss') && ($child['D']!=='dd'); // S_ D_
                $Sdd  = isset($child['S'],$child['D']) && ($child['S']!=='ss') && ($child['D']==='dd'); // S_ dd
                $ssD  = isset($child['S'],$child['D']) && ($child['S']==='ss') && ($child['D']!=='dd'); // ss D_
                $ssdd = isset($child['S'],$child['D']) && ($child['S']==='ss') && ($child['D']==='dd'); // ss dd
                $hi = $isClassic && ($SD || $Sdd || $ssD || $ssdd);
              @endphp
              <td class="{{ $hi ? 'highlight-9331' : '' }}">
                <div class="center pigeon {{ $cell['class'] }}">
                  <!-- Обновлённый SVG-голубь -->
                  <svg viewBox="0 0 120 120" aria-label="pigeon">
                    <!-- лапки -->
                    <path class="leg" d="M46,100 l-6,8 M62,100 l6,8" stroke-width="2" stroke="#cc6e4a"/>
                    <!-- тело -->
                    <ellipse class="body" cx="58" cy="70" rx="38" ry="30"/>
                    <path class="outline" d="M20,70 q38,-40 76,0 q-10,22 -38,28 q-28,-6 -38,-28 z"/>
                    <!-- крыло -->
                    <path class="wing" d="M28,70 q30,-18 60,0 q-10,18 -30,24 q-22,-4 -30,-24 z"/>
                    <path class="outline" d="M28,70 q30,-18 60,0 q-10,18 -30,24 q-22,-4 -30,-24 z"/>
                    <!-- голова -->
                    <circle class="body" cx="90" cy="44" r="13"/>
                    <circle class="outline" cx="90" cy="44" r="13"/>
                    <!-- пегие пятна (видны при P_) -->
                    <circle class="patch" cx="85" cy="38" r="6"/>
                    <circle class="patch" cx="70" cy="62" r="8"/>
                    <!-- клюв и глаз -->
                    <polygon class="beak" points="100,44 112,48 100,52"/>
                    <circle class="eye" cx="93" cy="41" r="2.2"/>
                  </svg>
                </div>
                <div class="label">
                  <div class="k">
                    @php
                      $genoStr = '';
                      foreach($computed['locusOrder'] as $L){ $genoStr .= $child[$L]; }
                    @endphp
                    {{ $genoStr }}
                  </div>
                  <div title="S_: тёмный; dd: осветление; P_: пегость">{{ $cell['label'] }}</div>
                  <div class="muted">{{ $cell['gametes'] }}</div>
                </div>
              </td>
            @endfor
          </tr>
        @endfor
      </table>
      </div>
    </div>

    <div class="card" style="margin-top:16px">
      <h3>Объяснение результата</h3>
      <p style="line-height:1.6">{{ $computed['explainText'] }}</p>
      <p class="muted" style="margin-top:8px">
        Это учебная модель: независимые локусы (без сцепления), полное доминирование для S и P, разбавление проявляется только при dd.
      </p>
    </div>

    <div class="grid2" style="margin-top:16px">
      <div class="card">
        <h3>Распределение генотипов</h3>
        <div class="k muted" style="margin-bottom:6px">{{ implode('|',$computed['locusOrder']) }} → %</div>
        <ul style="margin:0;padding-left:20px">
          @foreach($computed['genoFreq'] as $k=>$v)
            <li class="k">{{ $k }} → {{ $v }}%</li>
          @endforeach
        </ul>
      </div>
      <div class="card">
        <h3>Распределение фенотипов</h3>
        <canvas id="phenoChart" height="140"></canvas>
      </div>
    </div>
  @endif
</div>

<script>
function fillScenario(a,b){
  document.querySelector('input[name="parent1"]').value=a;
  document.querySelector('input[name="parent2"]').value=b;
}
function onModeChange(val){
  const p1=document.querySelector('input[name="parent1"]');
  const p2=document.querySelector('input[name="parent2"]');
  if(+val===2){ p1.maxLength=4; p2.maxLength=4; p1.placeholder='SsDd'; p2.placeholder='SsDd';
                if(p1.value.length>4) p1.value='SsDd'; if(p2.value.length>4) p2.value='SsDd'; }
  else       { p1.maxLength=6; p2.maxLength=6; p1.placeholder='SsDdPp'; p2.placeholder='SsDdPp';
                if(p1.value.length<6) p1.value='SsDdPp'; if(p2.value.length<6) p2.value='SsDdPp'; }
}
function setScale(px){
  document.documentElement.style.setProperty('--pigeon-size', px+'px');
  const lbl=document.getElementById('scaleLabel'); if(lbl) lbl.textContent = px+' px';
}
// Авто-очистка/нормализация на клиенте (минимальная)
document.querySelectorAll('input[name="parent1"],input[name="parent2"]').forEach(inp=>{
  inp.addEventListener('blur', ()=>{
    let v=inp.value.trim();
    v=v.replace(/[^SsDdPp]/g,'');
    const mode = +document.querySelector('select[name="mode"]').value;
    v=v.slice(0, mode===2?4:6);
    inp.value=v;
  });
});

// Сохранение состояния (mode/parents) в URL для шаринга
(function syncURL(){
  const form = document.querySelector('form[method="post"]');
  if(!form) return;
  const url = new URL(location.href);
  const p1 = document.querySelector('input[name="parent1"]').value;
  const p2 = document.querySelector('input[name="parent2"]').value;
  const mode = document.querySelector('select[name="mode"]').value;
  url.searchParams.set('mode', mode);
  url.searchParams.set('parent1', p1);
  url.searchParams.set('parent2', p2);
  history.replaceState({}, '', url);
})();

// PNG сохранение квадрата Пеннета
function savePng(){
  const node = document.querySelector('.punnett');
  if(!node) return;
  const s=document.createElement('script');
  s.src='https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
  s.onload=()=> {
    html2canvas(node, {scale:2}).then(canvas=>{
      const a=document.createElement('a');
      a.download='punnett.png';
      a.href=canvas.toDataURL('image/png');
      a.click();
    });
  };
  document.body.appendChild(s);
}

@if ($computed)
  const phenoLabels = {!! json_encode(array_keys($computed['phenoFreq'])) !!};
  const phenoData   = {!! json_encode(array_values($computed['phenoFreq'])) !!};
  (function(){
    const s=document.createElement('script');
    s.src='https://cdn.jsdelivr.net/npm/chart.js';
    s.onload=()=> {
      const ctx=document.getElementById('phenoChart').getContext('2d');
      new Chart(ctx,{type:'pie',data:{labels:phenoLabels,datasets:[{data:phenoData}]}});
    };
    document.body.appendChild(s);
  })();
@endif
</script>
</body>
</html>
