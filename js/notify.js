/* Bella Vista Oasis Spa - the ONE warning / validation / confirmation system.
   Load this before auth.js, booking.js, account.js and admin.js. It adds two globals:

   Notice   custom modal + toast (replaces alert() and confirm()): error, warning, success, info, confirm
   Validate the shared Gmail and 12-digit contact-number rules, field error states and the 0 / 12 counter

   Notice.error("Invalid Gmail Address","Please enter a valid Gmail address ending in @gmail.com.")   -> Promise, resolves when closed
   Notice.confirm({title,message,details:[["Service","Facial"]],confirmLabel,cancelLabel,danger:true,onConfirm:async()=>{...}}) -> Promise<true|false>
   Notice.toast("success","Reservation cancelled.")

   The modal is a native <dialog> opened with showModal(): focus is trapped inside it, the page behind it is inert and
   focus returns to the element that opened it. Only one modal exists at a time; identical requests are merged and
   different ones wait in a queue. These rules only give instant feedback. The PHP server validates everything again. */
const Validate=(()=>{
 /* Gmail only: letters, digits and _ % + - in the name, single dots between them, then exactly @gmail.com */
 const GMAIL=/^[A-Za-z0-9_%+-]+(?:\.[A-Za-z0-9_%+-]+)*@gmail\.com$/i;
 const MSG={
  gmailTitle:"Invalid Gmail Address",gmailMsg:"Please enter a valid Gmail address ending in @gmail.com.",
  contactTitle:"Invalid Contact Number",contactMsg:"Contact number must contain 11 digits (09…) or 12 digits (639…)."};
 const gmail=v=>typeof v==="string"&&v.length<=254&&GMAIL.test(v.trim());
 const contact=v=>{if(typeof v!=="string")return false;const d=v.replace(/\D/g,"");return /^(09\d{9}|639\d{9})$/.test(d);};   /* 11 digits (09…) or 12 digits (639…) */

 const wrap=i=>i&&i.closest&&i.closest(".f");
 function flag(inp,msg){if(!inp)return;inp.setAttribute("aria-invalid","true");const f=wrap(inp);
  if(f){f.classList.add("bad");f.classList.remove("ok");const e=f.querySelector(".err");if(e&&msg)e.textContent=msg}}
 function unflag(inp){if(!inp)return;inp.removeAttribute("aria-invalid");const f=wrap(inp);if(f)f.classList.remove("bad")}
 /* once a field is marked invalid, clear the mark the moment its value becomes valid */
 function live(inp,test){if(!inp)return;inp.addEventListener("input",()=>{if(inp.getAttribute("aria-invalid")==="true"&&test(inp.value))unflag(inp)})}

 /* Contact-number input: digits only, a "0 / 12" counter, typing stops at 12 digits.
    Pasting is cleaned (spaces, dashes and symbols removed) and the user is told. */
 function bindContact(inp){
  if(!inp||inp.dataset.bvc)return null;inp.dataset.bvc="1";
  inp.setAttribute("inputmode","numeric");inp.setAttribute("pattern","^(09[0-9]{9}|639[0-9]{9})$");inp.setAttribute("autocomplete","tel");
  if(!inp.placeholder)inp.placeholder="09XXXXXXXXX or 639XXXXXXXXX";
  const c=document.createElement("div");c.className="bvn-count";c.id=(inp.id||"contact")+"-count";inp.insertAdjacentElement("afterend",c);
  inp.setAttribute("aria-describedby",((inp.getAttribute("aria-describedby")||"")+" "+c.id).trim());
  const paint=()=>{const digits=inp.value.replace(/\D/g,"");const n=digits.length;c.textContent=n?n+" digits"+(contact(inp.value)?" - looks good":""):"09XX XXX XXXX (11 digits) or 639XX XXX XXXX (12 digits)";c.classList.toggle("ok",contact(inp.value));c.classList.toggle("over",n>12);
   if(inp.getAttribute("aria-invalid")==="true"&&contact(inp.value))unflag(inp)};
  inp.addEventListener("beforeinput",e=>{   /* typed characters: block non-digits, and stop after 12 digits */
   if(e.inputType!=="insertText"||e.data==null)return;
   const digits=inp.value.replace(/\D/g,"");
   const sel=inp.selectionEnd-inp.selectionStart;
   if(/[^0-9]/.test(e.data)||(sel===0&&digits.length>=12&&/[0-9]/.test(e.data)))e.preventDefault()});
  inp.addEventListener("input",e=>{
   const clean=inp.value.replace(/[^0-9]/g,"");
   if(clean!==inp.value){inp.value=clean;if(/paste|drop/i.test(e.inputType||""))Notice.toast("info","Only digits are allowed. Spaces, dashes and symbols were removed.")}
   paint()});
  paint();return{paint};
 }
 return{GMAIL,MSG,gmail,contact,flag,unflag,live,bindContact};
})();

const Notice=(()=>{
 const REDUCED=()=>matchMedia("(prefers-reduced-motion:reduce)").matches;
 const SVG=p=>`<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${p}</svg>`;
 const ICON={
  error:SVG('<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>'),
  warning:SVG('<path d="M12 4l9.5 16.5h-19z"/><path d="M12 10v4.2M12 17.2h.01"/>'),
  success:SVG('<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.8"/>'),
  info:SVG('<circle cx="12" cy="12" r="9"/><path d="M12 11v5.2M12 7.8h.01"/>'),
  confirm:SVG('<circle cx="12" cy="12" r="9"/><path d="M9.6 9.6a2.5 2.5 0 1 1 3.6 2.2c-.8.5-1.2 1-1.2 1.9M12 17.2h.01"/>')};
 const X=SVG('<path d="M6 6l12 12M18 6L6 18"/>');

 /* look: the site's own colours and fonts. Every value falls back to the site palette on pages that do not define the variables. */
 const CSS=`
html dialog.bvn{border:0;padding:0;margin:auto;background:transparent;max-width:none;width:min(500px,calc(100% - 24px));max-height:calc(100dvh - 24px);overflow:visible;color:var(--ink,#20251F);font:400 1rem/1.6 Jost,system-ui,sans-serif;opacity:0;transform:translateY(14px) scale(.98);transition:opacity .26s ease,transform .32s cubic-bezier(.22,1,.36,1)}
html dialog.bvn[open]{animation:bvnpop .34s cubic-bezier(.22,1,.36,1)}   /* no opacity here: when .bvn-in is removed the base opacity:0 transition plays the fade-out */
@keyframes bvnpop{from{opacity:0;transform:translateY(18px) scale(.94)}to{opacity:1;transform:none}}
@keyframes bvnfade{from{opacity:0}to{opacity:1}}
html dialog.bvn.bvn-in,html dialog.bvn.bvn-in[open]{opacity:1;transform:none}
html dialog.bvn::backdrop,html dialog.bvn[open]::backdrop{background:rgba(20,39,28,.62);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px);opacity:1;transition:opacity .26s ease;animation:bvnfade .26s ease}
html dialog.bvn.bvn-in::backdrop{opacity:1}
.bvn-card{--accent:#203A2A;--tint:#EDE5D6;position:relative;background:var(--iv,#F8F4EA);border:1px solid var(--gold,#C5A46D);border-top:3px solid var(--accent);border-radius:4px;padding:28px 28px 24px;box-shadow:0 24px 60px rgba(20,39,28,.35);max-height:calc(100dvh - 24px);overflow:auto;overscroll-behavior:contain}
.bvn-card[data-type=error]{--accent:#8a2f2f;--tint:#f6e4e0}.bvn-card[data-type=warning]{--accent:#8A6A28;--tint:#f1e2bd}
.bvn-card[data-type=success]{--accent:#2f6a40;--tint:#d6e3d3}.bvn-card[data-type=info]{--accent:#203A2A;--tint:#e4e9df}.bvn-card[data-type=confirm]{--accent:#203A2A;--tint:#EDE5D6}
.bvn-ic{display:grid;place-items:center;width:46px;height:46px;border-radius:50%;background:var(--tint);color:var(--accent);margin-bottom:14px}
.bvn-t{font:500 clamp(1.6rem,5vw,1.95rem)/1.1 "Cormorant Garamond",Georgia,serif;color:var(--forest,#203A2A);margin:0 44px 10px 0}
.bvn-m{margin:0;color:var(--ink,#20251F);white-space:pre-line;overflow-wrap:anywhere}
.bvn-m:empty{display:none}
.bvn-d{display:grid;grid-template-columns:minmax(96px,auto) 1fr;margin:16px 0 0;border-top:1px solid rgba(32,58,42,.14)}
.bvn-d dt,.bvn-d dd{margin:0;padding:8px 0;border-bottom:1px solid rgba(32,58,42,.1);font-size:.95rem}
.bvn-d dt{color:var(--mut,#4a5a4d);padding-right:16px}.bvn-d dd{color:var(--ink,#20251F);font-weight:500;overflow-wrap:anywhere;white-space:pre-line}
.bvn-c{margin-top:14px}.bvn-c:empty{display:none}
.bvn-a{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:10px;margin-top:22px}
.bvn-b{display:inline-flex;align-items:center;justify-content:center;gap:8px;font:500 .76rem Jost,system-ui,sans-serif;letter-spacing:.14em;text-transform:uppercase;padding:12px 22px;min-height:46px;min-width:96px;border-radius:2px;border:1px solid var(--gold,#C5A46D);background:var(--gold,#C5A46D);color:var(--deep,#14271C);cursor:pointer;transition:transform .3s cubic-bezier(.22,1,.36,1),background .25s,color .25s,border-color .25s,box-shadow .25s}
.bvn-b:hover{transform:translateY(-1px);background:#d4b374;border-color:#d4b374;box-shadow:0 8px 18px rgba(20,39,28,.16)}.bvn-b:active{transform:scale(.98)}
.bvn-b.sec{background:transparent;color:var(--forest,#203A2A);border-color:rgba(32,58,42,.55)}.bvn-b.sec:hover{background:var(--forest,#203A2A);color:#fff;border-color:var(--forest,#203A2A)}
.bvn-b.dng{background:transparent;color:#8a2f2f;border-color:#8a2f2f}.bvn-b.dng:hover{background:#8a2f2f;color:#fff;border-color:#8a2f2f}
.bvn-b:focus-visible,.bvn-x:focus-visible{outline:2px solid var(--gold,#C5A46D);outline-offset:3px}
.bvn-b[disabled]{opacity:.6;cursor:progress;transform:none;box-shadow:none}
.bvn-b .sp{width:14px;height:14px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:bvnspin .7s linear infinite}
@keyframes bvnspin{to{transform:rotate(360deg)}}
.bvn-x{position:absolute;top:10px;right:10px;width:44px;height:44px;display:grid;place-items:center;background:none;border:0;border-radius:2px;color:var(--mut,#4a5a4d);cursor:pointer;transition:color .25s,background .25s}.bvn-x:hover{color:var(--forest,#203A2A);background:rgba(32,58,42,.07)}.bvn-x[hidden]{display:none}
.bvn-card .rd{display:block;padding:7px 0}.bvn-card .rd input{margin-right:10px;accent-color:var(--forest,#203A2A)}.bvn-card legend{font-size:.84rem;color:var(--mut,#4a5a4d);margin-bottom:6px;padding:0}.bvn-card fieldset{border:0;padding:0;margin:0}
/* contact counter and field error state (used on every form) */
.bvn-count{font-size:.78rem;line-height:1.4;margin-top:5px;text-align:right;color:var(--mut,#4a5a4d);font-variant-numeric:tabular-nums;transition:color .25s}.bvn-count.ok{color:#2f6a40}.bvn-count.over{color:#8a2f2f}
input[aria-invalid="true"],select[aria-invalid="true"],textarea[aria-invalid="true"]{border-color:#8a2f2f!important}
input[aria-invalid="true"]:focus,select[aria-invalid="true"]:focus,textarea[aria-invalid="true"]:focus{box-shadow:0 0 0 3px rgba(138,47,47,.2)!important}
/* toasts */
.bvn-toasts{position:fixed;z-index:2147483000;inset:auto auto max(18px,env(safe-area-inset-bottom)) 50%;margin:0;padding:0;border:0;background:transparent;overflow:visible;height:auto;color:inherit;transform:translateX(-50%);width:min(440px,calc(100% - 24px));display:flex;flex-direction:column;gap:10px;pointer-events:none}.bvn-toasts:not(:popover-open){display:none}.bvn-toasts:not([popover]){display:flex}
.bvn-toast{--accent:#203A2A;pointer-events:auto;display:flex;align-items:flex-start;gap:12px;background:var(--iv,#F8F4EA);color:var(--ink,#20251F);border:1px solid var(--accent);border-radius:3px;padding:12px 8px 12px 14px;box-shadow:0 14px 34px rgba(20,39,28,.25);font:400 .95rem/1.5 Jost,system-ui,sans-serif;opacity:0;transform:translateY(10px);transition:opacity .25s ease,transform .3s cubic-bezier(.22,1,.36,1)}
.bvn-toast.in{opacity:1;transform:none}
.bvn-toast{position:relative;overflow:hidden}.bvn-toast.in::after{content:"";position:absolute;left:0;right:0;bottom:0;height:2px;background:var(--accent);opacity:.55;transform-origin:left;animation:bvntimer var(--ms,4500ms) linear forwards}
@keyframes bvntimer{to{transform:scaleX(0)}}.bvn-toast:hover::after{animation-play-state:paused}
.bvn-toast[data-type=error]{--accent:#8a2f2f}.bvn-toast[data-type=warning]{--accent:#8A6A28}.bvn-toast[data-type=success]{--accent:#2f6a40}
.bvn-toast .ti{flex:none;color:var(--accent);margin-top:1px}.bvn-toast .tm{flex:1;overflow-wrap:anywhere}
.bvn-toast button{flex:none;background:none;border:0;width:36px;height:36px;display:grid;place-items:center;color:var(--mut,#4a5a4d);cursor:pointer;border-radius:2px}.bvn-toast button:hover{background:rgba(32,58,42,.07)}.bvn-toast button:focus-visible{outline:2px solid var(--gold,#C5A46D)}
@media(max-width:520px){.bvn-card{padding:24px 20px 20px}.bvn-a{flex-direction:column-reverse}.bvn-b{width:100%}.bvn-d{grid-template-columns:1fr}.bvn-d dt{border-bottom:0;padding-bottom:0}}
@media(prefers-reduced-motion:reduce){.bvn-toast::after{animation:none!important}html dialog.bvn,html dialog.bvn::backdrop,.bvn-toast,.bvn-b{transition:none!important;animation:none!important}.bvn-b .sp{animation-duration:1.6s}}`;

 let dlg,card,els,cur=null,queue=[],tbox,uid=0;
 const key=o=>JSON.stringify([o.type,o.title,o.message,o.details||"",(o.actions||[]).map(a=>a.label)]);

 function build(){
  if(dlg)return;
  const st=document.createElement("style");st.id="bvn-css";st.textContent=CSS;document.head.appendChild(st);
  dlg=document.createElement("dialog");dlg.className="bvn";
  dlg.innerHTML=`<div class="bvn-card" data-type="info"><button type="button" class="bvn-x" aria-label="Close">${X}</button><div class="bvn-ic"></div><h2 class="bvn-t" id="bvn-t"></h2><p class="bvn-m" id="bvn-m"></p><dl class="bvn-d" hidden></dl><div class="bvn-c"></div><div class="bvn-a"></div></div>`;
  document.body.appendChild(dlg);
  card=dlg.firstElementChild;
  els={x:card.querySelector(".bvn-x"),ic:card.querySelector(".bvn-ic"),t:card.querySelector(".bvn-t"),m:card.querySelector(".bvn-m"),d:card.querySelector(".bvn-d"),c:card.querySelector(".bvn-c"),a:card.querySelector(".bvn-a")};
  els.x.onclick=()=>dismiss();
  /* Escape: we close it ourselves so it can fade out, and never while a request is running */
  dlg.addEventListener("cancel",e=>{e.preventDefault();dismiss()});
  /* keep Tab / Shift+Tab cycling inside the window (the browser would otherwise move on to its own address bar) */
  dlg.addEventListener("keydown",e=>{if(e.key!=="Tab")return;
   const f=[...dlg.querySelectorAll('button,input,select,textarea,a[href],[tabindex]:not([tabindex="-1"])')].filter(el=>!el.disabled&&!el.hidden&&el.getClientRects().length);
   if(!f.length){e.preventDefault();return}
   const a=document.activeElement,first=f[0],last=f[f.length-1];
   if(e.shiftKey&&(a===first||!dlg.contains(a))){e.preventDefault();last.focus()}else if(!e.shiftKey&&(a===last||!dlg.contains(a))){e.preventDefault();first.focus()}});
  dlg.addEventListener("click",e=>{if(e.target===dlg)dismiss()});   /* a click on the dimmed backdrop = Cancel, never Confirm */
 }

 function dismiss(){if(cur&&!cur.busy&&cur.o.dismissible!==false)finish(null)}

 function render(o){
  card.dataset.type=o.type;
  dlg.setAttribute("role",o.type==="success"||o.type==="info"?"dialog":"alertdialog");
  els.ic.innerHTML=ICON[o.type]||ICON.info;
  els.t.textContent=o.title||"";els.m.textContent=o.message||"";
  dlg.setAttribute("aria-labelledby","bvn-t");dlg.setAttribute("aria-describedby",o.message?"bvn-m":"");
  els.d.textContent="";els.d.hidden=!(o.details&&o.details.length);
  (o.details||[]).forEach(([k,v])=>{const dt=document.createElement("dt"),dd=document.createElement("dd");dt.textContent=k;dd.textContent=v==null||v===""?"—":String(v);els.d.append(dt,dd)});
  els.c.textContent="";if(o.content)els.c.appendChild(o.content);
  els.x.hidden=o.dismissible===false;
  els.a.textContent="";
  const acts=o.actions&&o.actions.length?o.actions:[{label:"OK",value:true,variant:"primary",focus:true}];
  o.buttons=acts.map(a=>{const b=document.createElement("button");b.type="button";b.className="bvn-b"+(a.variant==="secondary"?" sec":a.variant==="danger"?" dng":"");b.textContent=a.label;b.dataset.label=a.label;
   b.onclick=()=>choose(a,b);els.a.appendChild(b);return b});
  o.acts=acts;
 }

 async function choose(a,btn){
  if(!cur||cur.busy)return;
  if(!a.handler){finish(a.value);return}
  cur.busy=true;card.setAttribute("aria-busy","true");
  cur.o.buttons.forEach(b=>b.disabled=true);els.x.disabled=true;
  btn.innerHTML='<span class="sp" aria-hidden="true"></span><span></span>';btn.lastChild.textContent=a.busyLabel||a.label;
  let res;try{res=await a.handler()}catch(e){res=false;try{console.error(e)}catch{}}
  if(!cur)return;
  cur.busy=false;card.removeAttribute("aria-busy");
  if(res===false){   /* handler asked to stay open: put the buttons back */
   cur.o.buttons.forEach(b=>{b.disabled=false;b.textContent=b.dataset.label});els.x.disabled=false;btn.focus();return}
  finish(a.value);
 }

 function open(item){
  build();cur=item;const o=item.o;item.opener=document.activeElement;
  render(o);
  if(!dlg.open)dlg.showModal();
  dlg.classList.add("bvn-in");
  /* focus: the safe choice first (Cancel / Keep on a destructive confirm), otherwise the marked or only button */
  const i=o.acts.findIndex(a=>a.focus);(o.buttons[i>=0?i:o.buttons.length-1]||els.x).focus({preventScroll:true});
 }

 function finish(value){
  const item=cur;if(!item)return;
  const done=()=>{
   if(dlg.open)dlg.close();dlg.classList.remove("bvn-in");cur=null;
   const f=item.o.focusAfter||item.opener;try{if(f&&f.isConnected&&f.focus)f.focus({preventScroll:true})}catch{}
   item.resolve(value);
   const n=queue.shift();if(n)open(n)};
  dlg.classList.remove("bvn-in");
  if(REDUCED())done();else setTimeout(done,260);
  item.busy=true;   /* ignore any further clicks while it fades out */
 }

 function show(o){
  o=Object.assign({type:"info"},o);const k=key(o);
  if(cur&&cur.key===k)return cur.promise;               /* the same notice is already on screen: never stack a copy */
  const q=queue.find(x=>x.key===k);if(q)return q.promise;
  const item={o,key:k,busy:false};
  item.promise=new Promise(r=>item.resolve=r);
  if(cur)queue.push(item);else open(item);
  return item.promise;
 }

 const simple=type=>(title,message,opts)=>show(Object.assign({type,title,message},opts||{}));
 /* confirm(): resolves true when the confirm button is used, false for Cancel / Escape / close / backdrop.
    onConfirm (optional, async) runs while the dialog stays open with the buttons locked, so it can never run twice. */
 function confirm(o){
  if(typeof o==="string") o={title:"Please Confirm",message:o};
  const confirmA={label:o.confirmLabel||"Confirm",value:true,variant:o.danger?"danger":"primary",handler:o.onConfirm,busyLabel:o.busyLabel};
  const cancelA={label:o.cancelLabel||"Cancel",value:false,variant:o.danger?"primary":"secondary",focus:true};
  /* destructive: the safe button is the filled, pre-focused one. Otherwise the confirm button is the filled one. */
  if(!o.danger){confirmA.focus=!o.safeFocus;cancelA.focus=!!o.safeFocus}
  return show({type:o.type||(o.danger?"warning":"confirm"),title:o.title,message:o.message,details:o.details,content:o.content,focusAfter:o.focusAfter,dismissible:o.dismissible,actions:[cancelA,confirmA]}).then(v=>v===true);
 }

 /* prompt(): custom input dialog replacing native window.prompt() */
 function prompt(o){
  if(typeof o==="string") o={title:"Input Required",message:o};
  o=Object.assign({title:"Input Required",message:"",defaultValue:"",placeholder:"",confirmLabel:"Submit",cancelLabel:"Cancel",danger:false,multiline:false,required:false},o);
  const wrap=document.createElement("div");
  wrap.className="bvn-prompt-wrap";
  wrap.style.cssText="margin-top:14px";
  if(o.inputLabel){
   const lbl=document.createElement("label");
   lbl.textContent=o.inputLabel;
   lbl.style.cssText="display:block;font-size:.85rem;font-weight:500;color:var(--mut,#4a5a4d);margin-bottom:6px";
   wrap.appendChild(lbl);
  }
  const inp=document.createElement(o.multiline?"textarea":"input");
  if(!o.multiline) inp.type=o.inputType||"text";
  inp.className="bvn-prompt-inp";
  inp.value=o.defaultValue||"";
  if(o.placeholder) inp.placeholder=o.placeholder;
  if(o.maxLength) inp.maxLength=o.maxLength;
  inp.style.cssText="width:100%;box-sizing:border-box;padding:10px 14px;font:400 .95rem/1.5 Jost,system-ui,sans-serif;border:1px solid var(--gold,#C5A46D);border-radius:3px;background:#fff;color:var(--ink,#20251F);outline:none;transition:border-color .2s,box-shadow .2s";
  if(o.multiline){inp.rows=o.rows||3;inp.style.resize="vertical"}
  inp.addEventListener("focus",()=>{inp.style.borderColor="var(--forest,#203A2A)";inp.style.boxShadow="0 0 0 3px rgba(32,58,42,.12)"});
  inp.addEventListener("blur",()=>{inp.style.borderColor="var(--gold,#C5A46D)";inp.style.boxShadow="none"});
  const errP=document.createElement("p");
  errP.className="bvn-prompt-err";
  errP.style.cssText="color:#8a2f2f;font-size:.82rem;margin:6px 0 0;display:none";
  wrap.appendChild(inp);
  wrap.appendChild(errP);
  let val=null;
  const cancelA={label:o.cancelLabel||"Cancel",value:null,variant:"secondary",focus:false};
  const confirmA={label:o.confirmLabel||"Submit",value:"__SUBMIT__",variant:o.danger?"danger":"primary",focus:true,handler:async()=>{
   const v=inp.value.trim();
   if(o.required&&!v){
    errP.textContent=o.requiredMessage||"Please enter a value.";
    errP.style.display="block";
    inp.focus();
    return false;
   }
   val=inp.value;
   return true;
  }};
  if(!o.multiline){
   inp.addEventListener("keydown",e=>{
    if(e.key==="Enter"){
     e.preventDefault();
     const b=card.querySelector('.bvn-a button[data-label="'+(o.confirmLabel||"Submit")+'"]');
     if(b) b.click();
    }
   });
  }
  setTimeout(()=>{
   try{
    inp.focus();
    if(o.defaultValue) inp.select();
   }catch{}
  },100);
  return show({type:o.type||(o.danger?"warning":"info"),title:o.title,message:o.message,content:wrap,dismissible:o.dismissible!==false,actions:[cancelA,confirmA]}).then(v=>v==="__SUBMIT__"?val:null);
 }

 const alertNotice=(title,message)=>show({type:"info",title:typeof title==="string"?title:"Notice",message:typeof message==="string"?message:""});

 /* toast: small, non-blocking, for results that need no decision (success, short info) */
 function toast(type,message,ms){
  if(!tbox){build();tbox=document.createElement("div");tbox.className="bvn-toasts";tbox.setAttribute("role","region");tbox.setAttribute("aria-label","Notifications");tbox.setAttribute("popover","manual");document.body.appendChild(tbox)}
  const dup=[...tbox.children].find(t=>t.dataset.m===message&&t.dataset.type===type);if(dup)return;
  while(tbox.children.length>=4)tbox.firstElementChild.remove();
  const t=document.createElement("div");t.className="bvn-toast";t.dataset.type=type;t.dataset.m=message;t.setAttribute("role",type==="error"?"alert":"status");
  t.innerHTML=`<span class="ti">${ICON[type]||ICON.info}</span><span class="tm"></span><button type="button" aria-label="Dismiss notification">${X}</button>`;
  t.querySelector(".tm").textContent=message;tbox.appendChild(t);try{tbox.hidePopover()}catch{}try{tbox.showPopover()}catch{}   /* top layer: stays above any open window */
  const rm=()=>{t.classList.remove("in");setTimeout(()=>t.remove(),REDUCED()?0:260)};
  t.querySelector("button").onclick=rm;
  requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add("in")));
  const life=ms||(type==="error"?12000:type==="warning"?9000:6000);t.style.setProperty("--ms",life+"ms");
  let left=life,t0=Date.now(),to=setTimeout(rm,left);   /* hovering pauses the countdown, so a message can be read in peace */
  t.onmouseenter=()=>{clearTimeout(to);left-=Date.now()-t0};t.onmouseleave=()=>{t0=Date.now();to=setTimeout(rm,left)};
 }

 try{window.alert=msg=>{toast("warning",String(msg))}}catch{}

 return{show,confirm,prompt,alert:alertNotice,toast,error:simple("error"),warning:simple("warning"),success:simple("success"),info:simple("info"),
  isOpen:()=>!!cur||queue.length>0,close:()=>{if(cur&&!cur.busy)finish(null)}};
})();

if(typeof window !== "undefined"){
 window.Validate = Validate;
 window.Notice = Notice;
}

