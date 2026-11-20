/* Bella Vista Oasis Spa - account UI. This file only TALKS to the server (/api/auth/*). It never stores passwords or a "logged in" flag:
   authentication lives in an HttpOnly session cookie that JavaScript cannot read, and the server re-checks it on every request. */
const Auth=(()=>{
 const $=s=>document.querySelector(s),esc=s=>String(s).replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));
 const HDR={"Content-Type":"application/json","X-Requested-With":"bella-vista"};
 async function api(url,body,method){
  const m = method || (body ? "POST" : "GET");
  const isGet = (m === "GET" || m === "HEAD");
  try{
   const r=await fetch(url.replace(/^\//,""),{
    method: m,
    credentials: "same-origin",
    headers: HDR,
    body: isGet ? undefined : (body ? JSON.stringify(body) : undefined)
   });
   let d={};try{d=await r.json()}catch{}return{ok:r.ok,status:r.status,data:d}
  }catch{
   return{ok:false,status:0,data:{error:"We could not reach the account service. Please try again in a moment."}}
  }
 }
 let meP=null;const me=()=>meP||(meP=api("/api/auth/me").then(r=>r.ok?r.data:{authenticated:false,offline:true}));
 const reset=()=>{meP=null};
 /* mirrors the server rules for instant feedback; the server is the real check */
 const GMAIL=/^[A-Za-z0-9._%+-]+@gmail\.com$/i;
 const REQS=[["len","8 or more characters","at least 8 characters",p=>p.length>=8],["up","Uppercase letter","an uppercase letter",p=>/[A-Z]/.test(p)],["lo","Lowercase letter","a lowercase letter",p=>/[a-z]/.test(p)],["num","Number","a number",p=>/\d/.test(p)],["sp","Special character","a special character",p=>/[^A-Za-z0-9\s]/.test(p)]];
 const safeNext=()=>{const n=new URLSearchParams(location.search).get("next");return n&&/^[a-z-]+\.html(#[a-z-]+)?$/.test(n)?n:null};
 const q=encodeURIComponent("contact.html#booking");

 /* ----- shared header account menu + Book Now gate ----- */
 function weakNotice(s){try{if(!s||!s.authenticated||!sessionStorage.getItem("bvweak")||document.getElementById("bvweak"))return;
  const d=document.createElement("div");d.id="bvweak";d.setAttribute("role","alert");d.style.cssText="position:fixed;left:0;right:0;bottom:0;z-index:99999;background:#7a1f1f;color:#fff;padding:12px 16px;font:14px/1.4 sans-serif;text-align:center";
  d.innerHTML='You are signed in with a shared test password. <a style="color:#ffe9a8;text-decoration:underline" href="'+(s.user.isAdmin?"admin.html#settings":"account.html#settings")+'">Change it now</a> <button type="button" style="margin-left:12px;background:none;border:1px solid #fff;color:#fff;cursor:pointer">Dismiss</button>';
  d.querySelector("button").onclick=()=>d.remove();document.body.appendChild(d)}catch{}}
 function init(){
  me().then(s=>{weakNotice(s);
   const a=$("#acct"),m=$("#acctm");
   if(s.authenticated){
    if(m){
     const ad=s.user.isAdmin;m.textContent=ad?"Admin Dashboard":"My account";m.href=ad?"admin.html":"account.html";
     let mobOut=document.getElementById("acctm-out");
     if(!mobOut){
      mobOut=document.createElement("button");
      mobOut.id="acctm-out";
      mobOut.type="button";
      mobOut.className="btn mob ghost";
      mobOut.textContent="Sign Out";
      mobOut.style.marginTop="6px";
      m.insertAdjacentElement("afterend",mobOut);
      mobOut.onclick=e=>{if(e)e.preventDefault();logout();};
     }
    }
    if(a)a.innerHTML=`<button class="al" id="acb" aria-expanded="false" aria-haspopup="true" aria-controls="acm">Account <span aria-hidden="true">&#9662;</span></button><div class="acm" id="acm" role="menu"><div class="who">${esc(s.user.fullName)}<br>${esc(s.user.email)}</div>${s.user.isAdmin?`<a href="admin.html" role="menuitem">Admin Dashboard</a><a href="admin.html#b-all" role="menuitem">All Bookings</a>`:`<a href="account.html" role="menuitem">My Profile</a><a href="account.html#bookings" role="menuitem">My Appointments</a>`}<button type="button" id="lo" role="menuitem">Sign Out</button></div>`;
    const b=$("#acb"),menu=$("#acm");if(b){const t=o=>{menu.classList.toggle("open",o);b.setAttribute("aria-expanded",o)};
     b.onclick=e=>{e.stopPropagation();t(!menu.classList.contains("open"))};document.addEventListener("click",e=>{if(!menu.contains(e.target))t(false)});
     document.addEventListener("keydown",e=>{if(e.key=="Escape"&&menu.classList.contains("open")){t(false);b.focus()}});
     $("#lo").onclick=e=>{if(e)e.preventDefault();t(false);logout();}}
   } else {
    if(m){m.textContent="Login / Register";m.href="login.html";const mobOut=document.getElementById("acctm-out");if(mobOut)mobOut.remove();}
    if(a){
     a.innerHTML=`<button class="al" id="acb" aria-expanded="false" aria-haspopup="true" aria-controls="acm">Account <span aria-hidden="true">&#9662;</span></button><div class="acm" id="acm" role="menu"><a href="login.html" role="menuitem">Login</a><a href="register.html" role="menuitem">Create Account</a></div>`;
     const b=$("#acb"),menu=$("#acm");if(b){const t=o=>{menu.classList.toggle("open",o);b.setAttribute("aria-expanded",o)};
      b.onclick=e=>{e.stopPropagation();t(!menu.classList.contains("open"))};document.addEventListener("click",e=>{if(!menu.contains(e.target))t(false)});
      document.addEventListener("keydown",e=>{if(e.key=="Escape"&&menu.classList.contains("open")){t(false);b.focus()}});}
    }
   }});
  document.addEventListener("click",async e=>{
   const l=e.target.closest("a[href]");if(!l)return;const h=l.getAttribute("href");
   if(!/^(contact\.html)?#booking$/.test(h))return;
   e.preventDefault();const s=await me();
   if(s.authenticated&&s.user.isAdmin){location.href="admin.html#b-all";return}
   if(s.authenticated){if(location.pathname.endsWith("contact.html")){const t=$("#booking");if(t)t.scrollIntoView({behavior:"smooth"});history.replaceState(null,"","#booking")}else location.href="contact.html#booking"}
   else gate(s)});
 }
 function gate(s){
  const old=$("#dlg");if(old&&old.open)old.close();
  let d=$("#authdlg");if(!d){document.body.insertAdjacentHTML("beforeend",`<dialog id="authdlg" aria-labelledby="agt"><span class="eb">Reserve Your Experience</span><h3 id="agt">An account is required to make a reservation.</h3><p id="agp"></p><div class="hb"><a class="btn sm" href="login.html?next=${q}">Login</a><a class="btn sm line" href="register.html?next=${q}">Create account</a><button class="btn sm line" id="agx" type="button">Not now</button></div></dialog>`);d=$("#authdlg");$("#agx").onclick=()=>d.close();d.addEventListener("click",e=>{if(e.target===d)d.close()})}
  $("#agp").textContent=s&&s.offline?"The account service is not available right now. Please try again shortly.":"Please sign in or create a Bella Vista Oasis Spa account before making a reservation.";
  d.showModal()}
 async function logout(skipConfirm=false){
  const notifier = (typeof Notice !== "undefined" && Notice) || (typeof window !== "undefined" && window.Notice);
  if(!skipConfirm && notifier && typeof notifier.confirm === "function"){
   const s=await me();
   const isAdmin=s&&s.authenticated&&s.user&&s.user.isAdmin;
   const ok=await notifier.confirm({
    title:"Confirm Logout",
    message:isAdmin?"Are you sure you want to log out of your admin account?":"Are you sure you want to log out of your account?",
    confirmLabel:"Yes, Log Out",
    cancelLabel:"Stay Logged In",
    danger:true
   });
   if(!ok)return false;
  }
  if(notifier && typeof notifier.toast === "function") notifier.toast("info","Logging out...");
  const r=await api("/api/auth/logout",{});
  if(!r.ok){
   if(notifier && typeof notifier.error === "function") notifier.error("Logout Failed", (r.data&&r.data.error)||"Unable to sign out. Please try again.");
   return false;
  }
  reset();
  location.href="login.html";
  return true;
 }

 /* ----- password field renderer, strength meter, checklist ----- */
 const EYE='<svg class="on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.600-7 10-7 10 7 10 7-3.600 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.600 5.100A10 10 0 0 1 12 5c6.400 0 10 7 10 7a17 17 0 0 1-3.200 4M6.600 6.600A16 16 0 0 0 2 12s3.600 7 10 7a9.700 9.700 0 0 0 4.200-.9M9.900 9.900a3 3 0 0 0 4.200 4.200"/></svg>';
 function renderPw(){document.querySelectorAll("[data-pw]").forEach(el=>{
  const id=el.dataset.pw,meter=el.hasAttribute("data-meter"),ac=el.dataset.ac||"current-password";
  el.className="f";el.dataset.f=id;
  el.innerHTML=`<label for="${id}">${esc(el.dataset.label)}</label><div class="pw"><input id="${id}" name="${id}" type="password" autocomplete="${ac}" required><button type="button" class="eye" aria-label="Show ${esc(el.dataset.label.toLowerCase())}" aria-pressed="false">${EYE}</button></div><div class="err"></div>`+
  (meter?`<div class="meter" data-l="0" aria-live="polite"><span>Password strength</span><span class="dots"><i></i><i></i><i></i><i></i><i></i></span><b class="sl"></b></div><div class="warn" role="status"><b></b><span></span></div><div class="reqs-h">Password requirements</div><ul class="reqs">${REQS.map(r=>`<li data-r="${r[0]}">${r[1]}</li>`).join("")}</ul>`:"")+(id.startsWith("confirm")?'<div class="mt" role="status" aria-live="polite"></div>':"");
  const inp=el.querySelector("input"),eye=el.querySelector(".eye");
  eye.onclick=()=>{const s=inp.type==="password";inp.type=s?"text":"password";eye.classList.toggle("shown",s);eye.setAttribute("aria-pressed",s);eye.setAttribute("aria-label",(s?"Hide ":"Show ")+el.dataset.label.toLowerCase())};
  if(meter)inp.addEventListener("input",()=>strength(el,inp.value))})}
 function strength(el,p){
  const met=REQS.filter(r=>r[3](p)),n=p?met.length:0,m=el.querySelector(".meter"),w=el.querySelector(".warn");
  el.querySelectorAll(".reqs li").forEach((li,i)=>li.classList.toggle("ok",REQS[i][3](p)));
  m.querySelectorAll(".dots i").forEach((d,i)=>d.classList.toggle("on",i<n));
  const lvl=!p?0:n<=1?1:n<=3?2:n==4?3:4;m.dataset.l=lvl;m.querySelector(".sl").textContent=["","Weak","Fair","Good","Strong"][lvl];
  const miss=REQS.filter(r=>!r[3](p)).map(r=>r[2]);let t="",s="";
  if(p&&n<5){if(n<=2){t="Weak password";const l=miss.length>1?miss.slice(0,-1).join(", ")+" and "+miss[miss.length-1]:miss[0];s=`Add ${l} to make your password stronger.`}else if(n==3){t="Getting there";s=`You still need ${miss.join(" and ")}.`}else{t="Almost there";s=`Your password needs one more requirement: ${miss[0]}.`}}
  else if(p){t="Strong password";s="Your password meets all security requirements."}
  w.classList.toggle("show",!!p);if(p){w.querySelector("b").textContent=t;w.querySelector("span").textContent=s}}
 const pwErr=p=>!p?"Please enter a password.":p.length<8?"Password must contain at least 8 characters.":!/[A-Z]/.test(p)?"Password must contain an uppercase letter.":!/[a-z]/.test(p)?"Password must contain a lowercase letter.":!/\d/.test(p)?"Password must contain a number.":!/[^A-Za-z0-9\s]/.test(p)?"Password must contain a special character.":null;
 const fld=id=>document.querySelector(`[data-f="${id}"]`)||document.getElementById(id)?.closest(".f");
 function mark(id,msg){const f=fld(id);if(!f)return;f.classList.toggle("bad",!!msg);f.classList.toggle("ok",!msg&&!!f.querySelector("input").value);const e=f.querySelector(".err");if(e&&msg)e.textContent=msg}
 const banner=(form,msg,cls)=>{let b=form.querySelector(".msg");if(!b){b=document.createElement("div");b.className="msg";b.setAttribute("role","alert");form.prepend(b)}b.className="msg "+cls;b.textContent=msg;b.hidden=!msg};
 const busy=(btn,on,txt)=>{btn.disabled=on;if(on){btn.dataset.t=btn.textContent;btn.textContent=txt}else btn.textContent=btn.dataset.t||btn.textContent};
 const withQuery=h=>{const n=new URLSearchParams(location.search).get("next");return n?h+"?next="+encodeURIComponent(n):h};

 /* ----- page initialisers: each runs only if its form exists ----- */
 async function page(){
  renderPw();
  const s=await me();
  const L=$("#loginForm"),R=$("#registerForm"),F=$("#forgotForm"),X=$("#resetForm"),V=$("#verifyBox"),Ac=$("#accountBox");
  if(s.offline&&(L||R||F||X)){const f=L||R||F||X;banner(f,"The account service is not available right now. Please try again shortly.","aerr")}
  if(s.authenticated&&(L||R)){location.replace(safeNext()||"account.html");return}
  const lk=$("[data-to-register]");if(lk)lk.href=withQuery("register.html");const ll=$("[data-to-login]");if(ll)ll.href=withQuery("login.html");

  if(L){const em=$("#email"),pw=$("#password");
   L.addEventListener("submit",async e=>{e.preventDefault();banner(L,"","");
    const eOk=GMAIL.test(em.value.trim());mark("email",eOk?"":"Please enter a valid Gmail address.");mark("password",pw.value?"":"Please enter your password.");
    if(!eOk||!pw.value)return;const b=$("#go");busy(b,true,"Signing in...");
    const r=await api("/api/auth/login",{email:em.value,password:pw.value});busy(b,false);
    if(r.ok){
      if(window.Notice&&Notice.toast)Notice.toast("success","Login successful");
      try{r.data.user&&r.data.user.weakPassword?sessionStorage.setItem("bvweak","1"):sessionStorage.removeItem("bvweak")}catch{}
      reset();
      location.href=safeNext()||(r.data.user&&r.data.user.isAdmin?"admin.html":"account.html");
    }else{
      if(r.data.field==="email"||(r.data.error&&r.data.error.includes("Email not found"))){
        mark("email",r.data.error||"Email not found");
        em.focus();
      }else if(r.data.field==="password"||(r.data.error&&r.data.error.includes("Incorrect password"))){
        mark("password",r.data.error||"Incorrect password");
        pw.value="";
        pw.focus();
      }
      banner(L,r.data.error||"The email or password is incorrect.","aerr");
    }
   });
  }

  if(R){const pwEl=R.querySelector('[data-f="password"] input'),cf=R.querySelector('[data-f="confirmPassword"] input'),mt=R.querySelector(".mt"),em=$("#email");
   const match=()=>{if(!cf.value){mt.textContent="";mt.className="mt";return true}const ok=cf.value===pwEl.value;mt.textContent=ok?"\u2713 Passwords match":"Passwords do not match.";mt.className="mt"+(ok?" ok":"");return ok};
   pwEl.addEventListener("input",match);cf.addEventListener("input",match);
   R.addEventListener("submit",async e=>{e.preventDefault();banner(R,"","");
    const name=$("#fullName").value.trim(),ev=em.value.trim(),pe=pwErr(pwEl.value);
    mark("fullName",name?"":"Please enter your full name.");mark("email",GMAIL.test(ev)?"":"Please enter a valid Gmail address.");mark("password",pe||"");mark("confirmPassword",cf.value===pwEl.value?"":"Passwords do not match.");
    if(!name||!GMAIL.test(ev)||pe||cf.value!==pwEl.value)return;
    const b=$("#go");busy(b,true,"Creating account...");
    const r=await api("/api/auth/register",{fullName:name,email:ev,password:pwEl.value,confirmPassword:cf.value});busy(b,false);
    if(!r.ok){if(r.data.field)mark(r.data.field,r.data.error);else banner(R,r.data.error||"Something went wrong.","aerr");return}
    if(window.Notice&&Notice.toast)Notice.toast("success","Account created successfully");
    reset();if(r.data.needsVerification){R.hidden=true;$("#regDone").hidden=false}else location.href=safeNext()||"account.html"})}

  if(F){
   const em=$("#email"),pwEl=F.querySelector('[data-f="password"] input'),cf=F.querySelector('[data-f="confirmPassword"] input'),mt=F.querySelector(".mt");
   if(pwEl&&cf){
    const match=()=>{
     if(!cf.value){if(mt){mt.textContent="";mt.className="mt";}return true;}
     const ok=cf.value===pwEl.value;
     if(mt){mt.textContent=ok?"\u2713 Passwords match":"Passwords do not match.";mt.className="mt"+(ok?" ok":"");}
     return ok;
    };
    pwEl.addEventListener("input",match);cf.addEventListener("input",match);
   }

   F.addEventListener("submit",async e=>{
    e.preventDefault();banner(F,"","");
    const ev=em.value.trim();
    const eOk=GMAIL.test(ev);
    mark("email",eOk?"":"Please enter a valid Gmail address.");
    if(!eOk)return;

    // Direct password reset (Local environment / exact function of resetpassword.php)
    if(pwEl&&cf){
     const pVal=pwEl.value, cVal=cf.value;
     if(!pVal){mark("password","Please enter a new password.");pwEl.focus();return;}
     const pe=pwErr(pVal);if(pe){mark("password",pe);pwEl.focus();return;}
     mark("password","");
     if(pVal!==cVal){mark("confirmPassword","Passwords do not match.");cf.focus();return;}
     mark("confirmPassword","");

     const b=$("#go");busy(b,true,"Resetting password...");
     const r=await api("/api/auth/reset-direct",{email:ev,password:pVal,confirmPassword:cVal});
     busy(b,false);

     if(r.ok){
      if(window.Notice&&Notice.toast)Notice.toast("success","Password updated successfully");
      F.hidden=true;
      const fd=$("#forgotDone");
      fd.hidden=false;
     }else{
      if(r.data&&r.data.field){
       mark(r.data.field,r.data.error);
       if(r.data.field==="email")em.focus();
       else if(r.data.field==="password")pwEl.focus();
      }
      banner(F,(r.data&&r.data.error)||"Error updating password (email may not exist)","aerr");
     }
     return;
    }

    const b=$("#go");busy(b,true,"Processing...");const r=await api("/api/auth/forgot",{email:ev});busy(b,false);
    if(r.ok){F.hidden=true;$("#forgotDone").hidden=false;}
    else{if(r.data.field)mark(r.data.field,r.data.error);banner(F,r.data.error||"Something went wrong.","aerr");}
   });
  }

  if(X){
   const tk=(location.hash.match(/token=([a-f0-9]{64})/)||[])[1];
   const emailParam=(location.hash.match(/email=([^&]+)/)||[])[1];
   history.replaceState(null,"",location.pathname);

   const emField=$("#resetEmailField"),emInput=emField?emField.querySelector("input"):null;
   if(!tk){
    if(emField){
     emField.style.display="block";
     if(emailParam){
      emInput.value=decodeURIComponent(emailParam);
      mark("email","");
     }
    }else{
     X.hidden=true;$("#badLink").hidden=false;
    }
   }

   const pwEl=X.querySelector('[data-f="password"] input'),cf=X.querySelector('[data-f="confirmPassword"] input'),mt=X.querySelector(".mt");
   const match=()=>{if(!cf.value){mt.textContent="";mt.className="mt";return}const ok=cf.value===pwEl.value;mt.textContent=ok?"\u2713 Passwords match":"Passwords do not match.";mt.className="mt"+(ok?" ok":"")};
   pwEl.addEventListener("input",match);cf.addEventListener("input",match);

   X.addEventListener("submit",async e=>{e.preventDefault();banner(X,"","");
    const pe=pwErr(pwEl.value);
    if(emField&&!tk){
     const emVal=emInput.value.trim();
     mark("email",GMAIL.test(emVal)?"":"Please enter a valid Gmail address.");
     if(!GMAIL.test(emVal))return;
    }
    mark("password",pe||"");mark("confirmPassword",cf.value===pwEl.value?"":"Passwords do not match.");
    if(pe||cf.value!==pwEl.value)return;

    const b=$("#go");busy(b,true,"Saving...");
    let r;
    if(tk){
     r=await api("/api/auth/reset",{token:tk,password:pwEl.value,confirmPassword:cf.value});
    }else{
     r=await api("/api/auth/reset-direct",{email:emInput.value.trim(),password:pwEl.value,confirmPassword:cf.value});
    }
    busy(b,false);

    if(r.ok){
     if(window.Notice&&Notice.toast)Notice.toast("success","Password updated successfully");
     X.hidden=true;
     $("#resetDone").hidden=false;
    }else if(r.data&&r.data.field){
     mark(r.data.field,r.data.error);
     if(r.data.field==="email"&&emInput)emInput.focus();
     else if(r.data.field==="password"&&pwEl)pwEl.focus();
     banner(X,r.data.error||"Something went wrong.","aerr");
    }else{
     banner(X,(r.data&&r.data.error)||"Something went wrong.","aerr");
    }
   });
  }

  if(V){const tk=(location.hash.match(/token=([a-f0-9]{64})/)||[])[1];history.replaceState(null,"",location.pathname);
   const out=$("#verifyMsg");if(!tk){out.textContent="This verification link is invalid or has expired.";return}
   const r=await api("/api/auth/verify",{token:tk});reset();out.textContent=r.ok?"Your email address is verified. You can now log in and make a reservation.":(r.data.error||"This verification link is invalid or has expired.");$("#verifyGo").hidden=false}

  if(Ac){if(!s.authenticated){location.replace("login.html?next=account.html");return}
   $("#pName").textContent=s.user.fullName;$("#pEmail").textContent=s.user.email;
   const v=$("#pVer");v.textContent=!s.emailVerification?"Email verification is not enabled on this server.":s.user.emailVerified?"Email verified":"Email not yet verified";
   if(s.emailVerification&&!s.user.emailVerified){const rb=$("#resend");rb.hidden=false;rb.onclick=async()=>{rb.disabled=true;const r=await api("/api/auth/resend-verification",{});v.textContent=r.ok?"Verification email sent. Check your inbox.":(r.data.error||"Could not send.");}}
   $("#lo2").onclick=e=>{if(e)e.preventDefault();logout();};
   const r=await api("/api/bookings"),ul=$("#bkList");
   if(r.ok&&r.data.bookings.length)ul.innerHTML=r.data.bookings.map(b=>`<li><div><b>${esc(b.service)}</b><small>Preferred date: ${esc(b.date)}</small></div><div><small>Request #${b.id}</small><small>Status: ${esc(b.status)}</small></div></li>`).join("");
   else ul.innerHTML=`<li><div>${r.ok?"You have no appointment requests yet.":esc(r.data.error||"Could not load your requests.")}</div></li>`}
 }
 return{api,me,reset,init,page,gate,logout,weakNotice};
})();
