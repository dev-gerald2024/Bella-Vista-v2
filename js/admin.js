/* ADMIN DASHBOARD. All data lives on the server; every request is re-checked there for an admin session (users.role = ADMIN). */
(async()=>{
const msg=t=>{document.body.innerHTML='<p style="font:16px/1.6 sans-serif;max-width:520px;margin:15vh auto;padding:0 20px">'+t+'</p>'};
const SESSION=await Auth.me();
if(SESSION.offline)return msg("<b>The account service is not available right now.</b><br>Please try again shortly.");
if(!SESSION.authenticated){location.replace("login.html?next=admin.html");return}
if(!SESSION.user.isAdmin){location.replace("account.html");return}
Auth.weakNotice(SESSION);   /* customers have their own page */
const ld0=await DB.loadAdmin();
if(!ld0.ok)return msg("<b>We could not load the dashboard.</b><br>Please refresh the page.");
const $=s=>document.querySelector(s),esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const cap=s=>s?s[0]+s.slice(1).toLowerCase():"";
const ld=k=>{
  if(k==="bookings")return DB.allBookings().map(b=>({...b,service:b.serviceName,status:cap(b.status),paymentStatus:b.paymentStatus||"UNPAID",paidAmount:b.paidAmount||0,remainingBalance:b.remainingBalance!==undefined?b.remainingBalance:b.amount}));
  if(k==="res-rooms")return DB.rooms().map(r=>({...r,code:r.room_code,name:r.room_name,category:r.category_name||r.category_code,minGuests:r.min_guests,stdCap:r.standard_capacity,maxCap:r.max_capacity,status:r.room_status,desc:r.notes||""}));
  if(k==="res-pkgs")return DB.packages().map(p=>({...p,code:p.package_code,name:p.name,category:p.category_code,duration:p.default_duration,basePrice:p.base_price,extraGuestPrice:p.additional_guest_price,status:p.package_status,desc:p.description||""}));
  if(k==="res-th")return DB.therapists().map(t=>({...t,code:t.employee_code,name:t.full_name,phone:t.contact_number,email:t.email,specializations:t.specialization,status:t.employment_status,avail:t.availability_status}));
  return DB.content(k).slice();
};
const sv=async(k,v)=>{const r=await DB.saveContent(k,v);if(!r.ok)Notice.error("Save Failed",r.data.error||"Could not save. Please try again.");return r};
const log=async()=>{};   /* every admin action is recorded by the server in admin_log */
const U=SESSION.user;$("#who").textContent=U.fullName;
const peso=n=>"\u20B1"+Number(n||0).toLocaleString();
const TXT=[["name","Name"],["category","Category"],["desc","Description","textarea"]];
const SC={
 bookings:{t:"Booking",f:[["name","Customer name"],["phone","Phone"],["email","Email"],["service","Service"],["date","Date","date"],["time","Time","time"],["duration","Duration (minutes)","number"],["guests","Guests (1-6)","number"],["amount","Amount (\u20B1, optional, entered manually)","number"],["paymentStatus","Payment Status","select",["UNPAID","PARTIALLY_PAID","PAID","FORFEITED"]],["status","Status","select",["Pending","Confirmed","In_progress","Completed","Cancelled","No-show","Rejected"]]]},
 "res-rooms":{t:"Spa Room",f:[["code","Room Code (e.g. SR-1)"],["name","Room Name"],["category","Category","select",["Single Room Massage","Couples Suite","Family Spa Lounge"]],["minGuests","Min Guests","number"],["stdCap","Standard Capacity","number"],["maxCap","Max Capacity","number"],["status","Physical Status","select",["AVAILABLE","MAINTENANCE","INACTIVE"]],["desc","Notes","textarea"]]},
 "res-pkgs":{t:"Package",f:[["code","Package Code (e.g. PKG-SR)"],["name","Package Name"],["category","Category","select",["Single Room Massage","Couples Suite","Family Spa Lounge"]],["duration","Duration (minutes)","number"],["basePrice","Base Price (\u20B1)","number"],["extraGuestPrice","Extra Guest Price (\u20B1)","number"],["status","Status","select",["ACTIVE","INACTIVE"]],["desc","Description","textarea"]]},
 "res-th":{t:"Therapist",f:[["code","Employee Code (e.g. TH-001)"],["name","Full Name"],["phone","Phone"],["email","Email"],["specializations","Specializations (comma-separated)"],["status","Employment Status","select",["ACTIVE","INACTIVE"]],["avail","Availability Status","select",["AVAILABLE","ON_BREAK","OFF_DUTY","ON_LEAVE","INACTIVE"]]]},
 services:{t:"Service",f:TXT},rooms:{t:"Accommodation",f:TXT},wellness:{t:"Wellness item",f:TXT},
 faqs:{t:"FAQ",f:[["q","Question"],["a","Answer","textarea"]]},
 content:{t:"Content block",f:[["section","Section","select",["Brand statement","Address","Phone","Email","Opening hours","Accommodation information","Room amenities"]],["text","Text (Room amenities: one per line)","textarea"]]},
 gallery:{t:"Gallery image",f:[["title","Title (caption)"],["path","Image file path, e.g. assets/images/spa.jpg"]]}};
const HINT={wellness:"Items you add appear on the Wellness page under \"Wellness experiences\".",
 faqs:"Questions you add appear on the FAQs page after the built-in ones. A question worded exactly like a built-in one replaces its answer.",
 gallery:"Images appear in the Gallery section on the Home and About pages. First copy the image file into the project's assets/images folder, then enter its path here (for example assets/images/spa.jpg).",
 content:"Each block replaces one text on the public site: the footer, contact page, FAQ answers and accommodations. Use Edit to change a section that already has a block."};
const ICONS = {
  dash: `<svg viewBox="0 0 24 24"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>`,
  "b-all": `<svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>`,
  "b-pend": `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,
  "b-res": `<svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>`,
  "b-cal": `<svg viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>`,
  "b-arch": `<svg viewBox="0 0 24 24"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>`,
  "res-rooms": `<svg viewBox="0 0 24 24"><path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/></svg>`,
  "res-pkgs": `<svg viewBox="0 0 24 24"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>`,
  "res-th": `<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/></svg>`,
  services: `<svg viewBox="0 0 24 24"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>`,
  "res-clean": `<svg viewBox="0 0 24 24"><path d="m15 4-3 3"/><path d="m18 7-3 3"/><path d="M9 11 3 17l4 4 6-6"/><path d="m13 15 4 4"/></svg>`,
  "pay-dash": `<svg viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>`,
  "pay-txns": `<svg viewBox="0 0 24 24"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>`,
  "pay-sett": `<svg viewBox="0 0 24 24"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/><circle cx="8" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="10" cy="18" r="2"/></svg>`,
  customers: `<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
  reviews: `<svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`,
  reports: `<svg viewBox="0 0 24 24"><line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/></svg>`,
  content: `<svg viewBox="0 0 24 24"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>`,
  gallery: `<svg viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>`,
  faqs: `<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>`,
  notif: `<svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>`,
  log: `<svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>`,
  settings: `<svg viewBox="0 0 24 24"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>`
};

const NAV = [
  ["dash", "Dashboard"],
  ["BOOKING MANAGEMENT", [
    ["b-all", "All Bookings"],
    ["b-pend", "Pending Bookings"],
    ["b-res", "Reschedule Requests"],
    ["b-cal", "Booking Calendar"],
    ["b-arch", "Archived Bookings"]
  ]],
  ["SPA OPERATIONS", [
    ["res-rooms", "Spa Rooms"],
    ["res-pkgs", "Packages"],
    ["res-th", "Therapists"],
    ["services", "Services"],
    ["res-clean", "Cleaning & Buffer"]
  ]],
  ["PAYMENTS", [
    ["pay-dash", "Payment Overview"],
    ["pay-txns", "Transactions"],
    ["pay-sett", "Payment Rules"]
  ]],
  ["CUSTOMER & INSIGHTS", [
    ["customers", "Customers"],
    ["reviews", "Reviews"],
    ["reports", "Reports & Revenue"]
  ]],
  ["WEBSITE", [
    ["content", "Content"],
    ["gallery", "Gallery"],
    ["faqs", "FAQs"]
  ]],
  ["SYSTEM", [
    ["notif", "Notifications"],
    ["log", "Activity Log"],
    ["settings", "Settings"]
  ]]
];

const TT = {
  dash: "Dashboard",
  "b-all": "All Bookings",
  "b-pend": "Pending Bookings",
  "b-res": "Reschedule Requests",
  "b-cal": "Booking Calendar",
  "b-arch": "Archived Bookings",
  "res-rooms": "Spa Rooms",
  "res-pkgs": "Packages",
  "res-th": "Therapists",
  services: "Services",
  "res-clean": "Cleaning & Buffer",
  "pay-dash": "Payment Overview",
  "pay-txns": "Transactions",
  "pay-sett": "Payment Rules",
  customers: "Customers",
  reviews: "Reviews",
  reports: "Reports & Revenue",
  revenue: "Reports & Revenue",
  content: "Website Content",
  gallery: "Gallery Images",
  faqs: "Frequently Asked Questions",
  notif: "Notifications",
  log: "Activity Log",
  settings: "Admin Settings",
  rooms: "Website Accommodations",
  wellness: "Wellness Experiences"
};

let navMarkup = `<a class="nav-dash" href="#dash">${ICONS.dash}<span>Dashboard</span></a>`;
NAV.slice(1).forEach(([grpName, items], idx) => {
  const grpId = "grp-" + idx;
  navMarkup += `
    <div class="nav-group" data-grp="${grpId}">
      <div class="nav-group-header" role="button" tabindex="0" aria-expanded="true">
        <span class="nav-group-title">${esc(grpName)}</span>
        <svg class="nav-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="nav-items">
        ${items.map(([k, t]) => {
          TT[k] = t;
          return `<a class="nav-link" href="#${k}" data-k="${k}">${ICONS[k] || ""}<span>${esc(t)}</span></a>`;
        }).join("")}
      </div>
    </div>
  `;
});
$("#sn").innerHTML = navMarkup;

document.querySelectorAll(".nav-group-header").forEach(hdr => {
  const toggle = () => {
    const grp = hdr.closest(".nav-group");
    if (!grp) return;
    const isCollapsed = grp.classList.toggle("collapsed");
    hdr.setAttribute("aria-expanded", isCollapsed ? "false" : "true");
  };
  hdr.addEventListener("click", toggle);
  hdr.addEventListener("keydown", e => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      toggle();
    }
  });
});
const COLS={bookings:["name","service","date","time","guests","amount","paymentStatus","status"]},vis=k=>{const s=SC[k],c=COLS[k];return c?c.map(n=>s.f.find(f=>f[0]===n)):s.f.slice(0,3)};
const bk=()=>ld("bookings"),bkAll=()=>bk().concat(DB.archivedBookings().map(b=>({...b,service:b.serviceName,status:cap(b.status),paymentStatus:b.paymentStatus||"UNPAID",paidAmount:b.paidAmount||0,remainingBalance:b.remainingBalance!==undefined?b.remainingBalance:b.amount}))),when=d=>d?new Date(d+"T00:00").toLocaleDateString(undefined,{year:"numeric",month:"short",day:"numeric"}):"\u2014";
const cell=(r,[n,,t])=>t==="textarea"?esc((r[n]||"").slice(0,90)):n==="amount"?(r[n]?peso(r[n]):"\u2014"):n==="status"?`<span class="pill ${esc(r[n]).toLowerCase()}">${esc(r[n])}</span>`:n==="paymentStatus"?`<span class="pill ${(r[n]||"unpaid").toLowerCase()}">${esc((r[n]||"UNPAID").replace(/_/g," "))}</span>`:n==="date"?when(r[n]):n==="service"?esc(r[n]||"\u2014")+((r.roomName||r.therapistName)?`<br><small style="color:var(--forest)">${r.roomName?"🚪 "+esc(r.roomName):""}${r.roomName&&r.therapistName?" · ":""}${r.therapistName?"👤 "+esc(r.therapistName):""}</small>`:""):esc(r[n]||"\u2014");
const empty=m=>`<div class="empty">${m}</div>`,table=(h,rows)=>`<div class="tw"><table><thead><tr>${h.map(x=>`<th>${x}</th>`).join("")}</tr></thead><tbody>${rows}</tbody></table></div>`;
/* ---- search, filter and pagination for every long list. Search/filter/page/rows-per-page are remembered per list. ---- */
const GS={},GR={},SIZES=[10,25,50],gst=k=>GS[k]||(GS[k]={q:"",f:{},p:1,n:10}),gk=el=>el.closest("[data-g]").dataset.g;
const opt=(v,t,cur)=>`<option value="${esc(v)}"${String(cur)===String(v)?" selected":""}>${esc(t)}</option>`;
const flat=o=>Object.values(o).filter(v=>v!==null&&typeof v!=="object").join(" ").toLowerCase();
function grid(k,cfg){GR[k]=cfg;const st=gst(k);
 return `<div class="tools" data-g="${k}" role="search"><input type="search" class="gq" placeholder="${esc(cfg.ph||"Search")}" aria-label="${esc(cfg.ph||"Search")}" value="${esc(st.q)}">${(cfg.fl||[]).map(f=>`<select class="gf" data-f="${f.k}" aria-label="${esc(f.t)}">${f.o.map(([v,t])=>opt(v,t,st.f[f.k]||"")).join("")}</select>`).join("")}<button type="button" class="btn s" data-a="gclr">Clear</button></div><div id="gb">${gbody(k)}</div>`}
function gbody(k){const cfg=GR[k],st=gst(k),q=st.q.trim().toLowerCase();
 const rows=cfg.items.filter(x=>(!q||x.s.includes(q))&&Object.entries(st.f).every(([n,v])=>!v||x.f[n]===v));
 if(!rows.length)return empty(q||Object.values(st.f).some(Boolean)?"No records match your search or filter.":cfg.empty);
 const pages=Math.max(1,Math.ceil(rows.length/st.n));if(st.p>pages)st.p=pages;if(st.p<1)st.p=1;
 const a=(st.p-1)*st.n,part=rows.slice(a,a+st.n),h=part.map(x=>x.h).join("");
 return (cfg.list?`<ul class="l">${h}</ul>`:table(cfg.head,h))+pager(k,rows.length,a,part.length,pages)}
function pager(k,total,a,n,pages){const st=gst(k),nums=[];
 for(let i=1;i<=pages;i++)if(i===1||i===pages||Math.abs(i-st.p)<=1)nums.push(i);else if(nums[nums.length-1]!=="…")nums.push("…");
 return `<nav class="pg" aria-label="Pagination" data-g="${k}"><span>Showing ${a+1}–${a+n} of ${total}</span><span class="pr"><label>Rows <select class="gn" aria-label="Rows per page">${SIZES.map(z=>opt(z,z,st.n)).join("")}</select></label>`
  +(pages>1?`<button type="button" class="btn s" data-a="gp" data-v="${st.p-1}"${st.p===1?" disabled":""}>&larr; Prev</button>`+nums.map(i=>i==="…"?`<span class="el">…</span>`:`<button type="button" class="btn s${i===st.p?" on":""}" data-a="gp" data-v="${i}"${i===st.p?' aria-current="page"':""}>${i}</button>`).join("")+`<button type="button" class="btn s" data-a="gp" data-v="${st.p+1}"${st.p===pages?" disabled":""}>Next &rarr;</button>`:"")+`</span></nav>`}
const STATUS_OPT=[["","All statuses"],["Pending","Pending"],["Confirmed","Confirmed"],["In_progress","In Progress"],["Completed","Completed"],["Cancelled","Cancelled"],["No-show","No-show"],["Rejected","Rejected"]];
function list(k,flt){const s=SC[k],all=ld(k),rows=all.filter(flt||(()=>1)),bkg=k==="bookings",td=day(new Date());
 const hint=bkg?"":(k==="services"||k==="rooms")?`<p class="demo">Items you add here appear on the public site${k==="services"?" and in the booking list":""}. An item with the same name as a built-in one replaces its text. Built-in items cannot be removed from here.</p>`:`<p class="demo">${esc(HINT[k]||"")}</p>`;
 const items=rows.map(r=>({h:`<tr>${vis(k).map(f=>`<td>${cell(r,f)}</td>`).join("")}<td class="a">${bkg?bact(r):""}<button class="btn s" data-a="edit" data-k="${k}" data-i="${r.id}">Edit</button><button class="btn s d" data-a="del" data-k="${k}" data-i="${r.id}">${bkg?"Archive":"Delete"}</button></td></tr>`,
  s:flat(r),f:{status:r.status,when:bkg?(r.date===td?"today":r.date>td?"upcoming":"past"):"",category:r.category||""}}));
 const cats=[...new Set(rows.map(r=>r.category).filter(Boolean))].sort(),fl=bkg?[{k:"status",t:"Status",o:k==="bookings"&&flt?[["","All"]]:STATUS_OPT},{k:"when",t:"Date",o:[["","All dates"],["today","Today"],["upcoming","Upcoming"],["past","Past"]]}]:cats.length?[{k:"category",t:"Category",o:[["","All categories"]].concat(cats.map(c=>[c,c]))}]:[];
 if(bkg&&flt)fl.shift();   /* the Pending list is already one status */
 return hint+`<div class="bar"><span>${rows.length} ${rows.length===1?"record":"records"}</span><button class="btn" data-a="add" data-k="${k}">Add ${s.t.toLowerCase()}</button></div>`+grid(bkg&&flt?"b-pend":k,{ph:bkg?"Search name, email, phone, service or booking ID":"Search "+s.t.toLowerCase()+"s",head:vis(k).map(f=>f[1].replace(/ \(.*/,"")).concat(["Actions"]),items,fl,empty:"Nothing here yet. Use the button above to add the first record."})}
const stBtn=(r,v,t,cls)=>`<button class="btn s${cls?" "+cls:""}" data-a="st" data-k="bookings" data-i="${r.id}" data-v="${v}">${t}</button>`;
const bact=r=>((r.status==="Pending"||r.status==="Confirmed")?`<button class="btn s" data-a="asgn" data-i="${r.id}">Assign</button>`:"")+
  (r.status==="Pending"
  ?stBtn(r,"Confirmed","Confirm")+stBtn(r,"Rejected","Reject","d")+stBtn(r,"Cancelled","Cancel","d")
  :r.status==="Confirmed"
    ?stBtn(r,"In_progress","Start")+stBtn(r,"Completed","Complete")+stBtn(r,"No-show","No-show","d")+stBtn(r,"Cancelled","Cancel","d")
    :r.status==="In_progress"
      ?stBtn(r,"Completed","Complete")+stBtn(r,"Cancelled","Cancel","d")
      :"");
const day=d=>d.toISOString().slice(0,10);let cm=new Date();cm.setDate(1);
const V={
 dash(){const b=bk(),rev=bkAll().filter(x=>/Confirmed|Completed/.test(x.status)).reduce((a,x)=>a+Number(x.amount||0),0),today=b.filter(x=>x.date===day(new Date()));
  const unasgn=b.filter(x=>/Pending|Confirmed/.test(x.status)&&!x.roomName).length;
  return (DB.live()&&DB.checks().some(c=>!c.ok)?`<p class="demo" style="color:#a33;font-weight:600">${DB.checks().filter(c=>!c.ok).length} setup item(s) need attention for a live site. <a href="#settings">Open Settings</a></p>`:"")+
  (unasgn>0?`<p class="demo" style="color:#8A6A28;font-weight:600;background:#fcf8ee;padding:10px 14px;border:1px solid #e2d1a6">Notice: <b>${unasgn}</b> active booking(s) need a room or therapist assigned. Go to <a href="#b-all">All Bookings</a> to assign resources.</p>`:"")+
  `<p class="demo">Live data from the spa server.</p><div class="cards"><div class="card"><small>Total bookings</small><b>${b.length}</b></div><div class="card"><small>Pending</small><b>${b.filter(x=>x.status==="Pending").length}</b></div><div class="card"><small>Confirmed</small><b>${b.filter(x=>x.status==="Confirmed").length}</b></div><div class="card"><small>Spa Rooms</small><b>${DB.rooms().length}</b></div><div class="card"><small>Active Therapists</small><b>${DB.therapists().filter(x=>x.employment_status==="ACTIVE").length}</b></div><div class="card"><small>Customers</small><b>${cust().length}</b></div><div class="card"><small>Revenue (entered)</small><b>${peso(rev)}</b></div></div>
  <h2 class="sec">Today's bookings</h2>${today.length?`<ul class="l">${today.map(x=>`<li>${esc(x.name)} \u2014 ${esc(x.service||"No service")}${x.roomName?" \u00B7 Room: "+esc(x.roomName):""}<small>${esc(x.status)}</small></li>`).join("")}</ul>`:empty("No bookings today.")}
  <h2 class="sec">Recent activity</h2>${V.log(5)}`},
 "b-all":()=>list("bookings"),"b-arch"(){const a=DB.archivedBookings();return `<p class="demo">Archived bookings are kept with their payment records and still count in reports and revenue. Restore returns one to All Bookings.</p>`+grid("b-arch",{ph:"Search archived bookings",head:["Customer","Service","Date","Amount","Status","Actions"],empty:"No archived bookings.",fl:[{k:"status",t:"Status",o:STATUS_OPT}],
  items:a.map(r=>({h:`<tr><td>${esc(r.name)}</td><td>${esc(r.serviceName)}</td><td>${when(r.date)}</td><td>${r.amount?peso(r.amount):"—"}</td><td><span class="pill ${esc(r.status).toLowerCase()}">${esc(cap(r.status))}</span></td><td class="a"><button class="btn s" data-a="restore" data-i="${esc(r.id)}">Restore</button><button class="btn s d" data-a="del-perm" data-i="${esc(r.id)}" style="margin-left:4px">Delete Permanently</button></td></tr>`,s:flat(r),f:{status:cap(r.status)}}))})},"b-pend":()=>list("bookings",r=>r.status==="Pending"),
 "b-cal"(){const y=cm.getFullYear(),m=cm.getMonth(),n=new Date(y,m+1,0).getDate(),s=cm.getDay(),b=bk();let c=["Sun","Mon","Tue","Wed","Thu","Fri","Sat"].map(d=>`<div class="h">${d}</div>`).join("")+'<div class="n"></div>'.repeat(s);
  for(let d=1;d<=n;d++){const k=`${y}-${String(m+1).padStart(2,"0")}-${String(d).padStart(2,"0")}`;c+=`<div><b>${d}</b>${b.filter(x=>x.date===k).map(x=>`<span class="i ${esc(x.status).toLowerCase()}" title="${esc(x.name)}">${esc(x.name)}</span>`).join("")}</div>`}
  return `<div class="bar"><button class="btn s" data-a="cal" data-v="-1">&larr; Prev</button><h2>${cm.toLocaleDateString(undefined,{month:"long",year:"numeric"})}</h2><button class="btn s" data-a="cal" data-v="1">Next &rarr;</button></div><div class="cal">${c}</div>`},
 "res-rooms"(){
  const rooms=DB.rooms();
  return `<p class="demo">Physical spa rooms inventory. Base physical state (Available, Maintenance, Inactive) combined with appointment schedule and 30-minute cleaning determines real-time live availability.</p>
  <div class="bar"><span>${rooms.length} ${rooms.length===1?"room":"rooms"}</span><button class="btn" data-a="add" data-k="res-rooms">Add Room</button></div>`+
  grid("res-rooms",{
    ph:"Search room code, name, category",
    head:["Code","Name","Category","Capacity","Status","Live State","Actions"],
    empty:"No rooms configured yet.",
    fl:[{k:"category",t:"Category",o:[["","All categories"],["Single Room Massage","Single Room"],["Couples Suite","Couples Suite"],["Family Spa Lounge","Family Spa Lounge"]]},{k:"status",t:"Status",o:[["","All statuses"],["AVAILABLE","Available"],["MAINTENANCE","Maintenance"],["INACTIVE","Inactive"]]}],
    items:rooms.map(r=>({
      h:`<tr><td><b>${esc(r.room_code)}</b></td><td>${esc(r.room_name)}</td><td>${esc(r.category_name||r.category_code)}</td><td>${r.min_guests===r.max_capacity?r.max_capacity:r.min_guests+"–"+r.max_capacity} guests</td><td><span class="pill ${r.room_status.toLowerCase()}">${esc(r.room_status)}</span></td><td><span class="pill ${(r.liveStatus||r.room_status).toLowerCase()}">${esc(r.liveStatus||r.room_status)}</span></td><td class="a"><button class="btn s" data-a="edit" data-k="res-rooms" data-i="${r.id}">Edit</button><button class="btn s" data-a="maint" data-i="${r.id}">Maintenance</button><button class="btn s${r.room_status==="INACTIVE"?"":" d"}" data-a="deact-room" data-i="${r.id}" data-v="${r.room_status==="INACTIVE"?"AVAILABLE":"INACTIVE"}">${r.room_status==="INACTIVE"?"Reactivate":"Deactivate"}</button></td></tr>`,
      s:flat(r),
      f:{category:r.category_name||r.category_code,status:r.room_status}
    }))
  });
 },
 "res-pkgs"(){
  const pkgs=DB.packages();
  return `<p class="demo">Documented spa packages and room categories. Rates are configured independently (PACKAGE_PRICING_RELATIONSHIP = 'INDEPENDENT') and never double-counted with treatment prices.</p>
  <div class="bar"><span>${pkgs.length} ${pkgs.length===1?"package":"packages"}</span><button class="btn" data-a="add" data-k="res-pkgs">Add Package</button></div>`+
  grid("res-pkgs",{
    ph:"Search package code, name, category",
    head:["Code","Package Name","Category","Duration","Base Rate","Extra Guest Rate","Status","Actions"],
    empty:"No packages configured.",
    items:pkgs.map(p=>({
      h:`<tr><td><b>${esc(p.package_code)}</b></td><td>${esc(p.name)}</td><td>${esc(p.category_code)}</td><td>${p.default_duration} mins</td><td>${peso(p.base_price)}</td><td>${p.additional_guest_price>0?peso(p.additional_guest_price)+" / guest":"—"}</td><td><span class="pill ${p.package_status.toLowerCase()}">${esc(p.package_status)}</span></td><td class="a"><button class="btn s" data-a="edit" data-k="res-pkgs" data-i="${p.id}">Edit</button><button class="btn s${p.package_status==='ACTIVE'?' d':''}" data-a="deact-pkg" data-i="${p.id}" data-v="${p.package_status==='ACTIVE'?'INACTIVE':'ACTIVE'}">${p.package_status==='ACTIVE'?'Deactivate':'Reactivate'}</button></td></tr>`,
      s:flat(p),
      f:{status:p.package_status}
    }))
  });
 },
 "res-th"(){
  const ths=DB.therapists();
  return `<p class="demo">Registered spa therapists, assigned shifts, meal breaks, and leaves. Operating shift bounds are 1:00 PM – 9:00 PM (Asia/Manila).</p>
  <div class="bar"><span>${ths.length} ${ths.length===1?"therapist":"therapists"}</span><button class="btn" data-a="add" data-k="res-th">Add Therapist</button></div>`+
  grid("res-th",{
    ph:"Search therapist code, name, specialization",
    head:["Code","Name","Contact","Specialization","Employment","Availability","Actions"],
    empty:"No therapists found.",
    items:ths.map(t=>({
      h:`<tr><td><b>${esc(t.employee_code)}</b></td><td>${esc(t.full_name)}</td><td>${esc(t.contact_number||"—")}<br><small>${esc(t.email||"")}</small></td><td>${esc(t.specialization||"All Services")}</td><td><span class="pill ${t.employment_status.toLowerCase()}">${esc(t.employment_status)}</span></td><td><span class="pill ${t.availability_status.toLowerCase()}">${esc(t.availability_status)}</span></td><td class="a"><button class="btn s" data-a="edit" data-k="res-th" data-i="${t.id}">Edit</button><button class="btn s" data-a="sched" data-i="${t.id}">Schedule &amp; Breaks</button><button class="btn s${t.employment_status==='ACTIVE'?' d':''}" data-a="deact-th" data-i="${t.id}" data-v="${t.employment_status==='ACTIVE'?'INACTIVE':'ACTIVE'}">${t.employment_status==='ACTIVE'?'Deactivate':'Reactivate'}</button></td></tr>`,
      s:flat(t),
      f:{status:t.employment_status}
    }))
  });
 },
 "res-clean"(){
  const cleanings=DB.cleanings();
  const rooms=DB.rooms();
  const roomCards=rooms.map(r=>`
    <div class="card" style="padding:14px">
      <small>${esc(r.room_code)} · ${esc(r.category_name||r.category_code)}</small>
      <b style="font-size:1.3rem;display:block;margin:4px 0">${esc(r.room_name)}</b>
      <div><span class="pill ${(r.liveStatus||r.room_status).toLowerCase()}">${esc(r.liveStatus||r.room_status)}</span></div>
      <small style="margin-top:6px">Cap: ${r.min_guests}–${r.max_capacity} guests</small>
    </div>
  `).join("");

  return `<h2 class="sec" style="margin-top:0">Room Operational States</h2>
  <div class="cards" style="margin-bottom:24px">${roomCards}</div>
  <h2 class="sec">30-Minute Cleaning Buffer Log</h2>
  <p class="demo">Every completed treatment triggers a mandatory 30-minute cleaning buffer. Early cleaning completion logs the task but preserves the buffer for schedule availability.</p>`+
  grid("res-clean",{
    ph:"Search room, booking, cleaner",
    head:["ID","Room","Booking ID","Treatment End","Cleaning End (Buffer)","Status","Cleaned By","Actions"],
    empty:"No cleaning tasks recorded.",
    items:cleanings.map(c=>({
      h:`<tr><td>#${c.id}</td><td><b>${esc(c.room_code||"Room "+c.room_id)}</b></td><td>${esc(c.booking_id)}</td><td>${esc(c.treatment_end_time||"—")}</td><td><b>${esc(c.cleaning_end_time||"—")}</b></td><td><span class="pill ${c.cleaning_status.toLowerCase()}">${esc(c.cleaning_status)}</span></td><td>${esc(c.cleaned_by||"Pending")}${c.cleaning_notes?`<br><small>${esc(c.cleaning_notes)}</small>`:""}</td><td class="a"><button class="btn s" data-a="upd-clean" data-i="${c.id}">Update Status</button></td></tr>`,
      s:flat(c),
      f:{status:c.cleaning_status}
    }))
  });
 },
 "pay-dash"(){
  const a=DB.paymentAnalytics()||{};
  const m=a.methods||{};
  return `
   <div class="sim-alert">SIMULATED PAYMENT TOTALS — NOT ACTUAL REVENUE</div>
   <div class="demo-banner">DEMO PAYMENT SYSTEM — NO REAL MONEY WILL BE TRANSFERRED.<br>All customer transactions, wallets, bank accounts, and receipts are local simulations.</div>
   <div class="cards">
    <div class="card"><small>Total Simulated Payments</small><b style="color:var(--forest)">${peso(a.totalSimulatedPayments)}</b></div>
    <div class="card"><small>Pending Cash at Spa</small><b style="color:#b45309">${peso(a.pendingCashAmount)}</b></div>
    <div class="card"><small>Total Simulated Refunds</small><b style="color:#991b1b">${peso(a.totalRefundedAmount)}</b></div>
    <div class="card"><small>Forfeited Downpayments</small><b style="color:#991b1b">${peso(a.totalForfeitedAmount)}</b></div>
    <div class="card"><small>Fully Paid Bookings</small><b>${a.fullyPaidBookingsCount||0}</b></div>
    <div class="card"><small>Partially Paid</small><b>${a.partiallyPaidBookingsCount||0}</b></div>
    <div class="card"><small>Unpaid Bookings</small><b>${a.unpaidBookingsCount||0}</b></div>
   </div>
   <h2 class="sec">Payment Method Summary (Simulated)</h2>
   <div class="cards">
    <div class="card"><small>GCash Demo</small><b>${m.GCASH_DEMO?m.GCASH_DEMO.count:0} txns</b><small style="color:var(--forest);font-weight:600">${peso(m.GCASH_DEMO?m.GCASH_DEMO.amount:0)}</small></div>
    <div class="card"><small>Maya Demo</small><b>${m.MAYA_DEMO?m.MAYA_DEMO.count:0} txns</b><small style="color:var(--forest);font-weight:600">${peso(m.MAYA_DEMO?m.MAYA_DEMO.amount:0)}</small></div>
    <div class="card"><small>Cash at Spa (Confirmed)</small><b>${m.CASH_AT_SPA?m.CASH_AT_SPA.count:0} txns</b><small style="color:var(--forest);font-weight:600">${peso(m.CASH_AT_SPA?m.CASH_AT_SPA.amount:0)}</small></div>
    <div class="card"><small>Bank Transfer Demo</small><b>${m.BANK_TRANSFER_DEMO?m.BANK_TRANSFER_DEMO.count:0} txns</b><small style="color:var(--forest);font-weight:600">${peso(m.BANK_TRANSFER_DEMO?m.BANK_TRANSFER_DEMO.amount:0)}</small></div>
   </div>
   <h2 class="sec">Recent Demo Transactions</h2>
   ${V["pay-txns"](true)}
  `;
 },
 "pay-txns"(isMini){
  const txns=DB.allTxns()||[];
  const head=["Ref","Booking","Customer","Method","Type","Amount","Status","Actions"];
  const STATUS_TXN_OPT=[["","All statuses"],["PAID","Paid"],["PENDING","Pending"],["REFUNDED","Refunded"],["PARTIALLY_REFUNDED","Partially Refunded"],["FORFEITED","Forfeited"],["FAILED","Failed"],["CANCELLED","Cancelled"]];
  const METHOD_OPT=[["","All methods"],["GCASH_DEMO","GCash Demo"],["MAYA_DEMO","Maya Demo"],["CASH_AT_SPA","Cash at Spa"],["BANK_TRANSFER_DEMO","Bank Transfer Demo"]];
  const items=txns.map(t=>({
   h:`<tr>
    <td><b>${esc(t.transactionRef||t.id)}</b></td>
    <td>${esc(t.bookingId)}</td>
    <td>${esc(t.customerId||"—")}</td>
    <td>${esc(t.paymentMethodLabel||t.paymentMethod||"Demo")}</td>
    <td><small>${esc((t.transactionType||"DOWNPAYMENT").replace(/_/g," "))}</small></td>
    <td><b>${peso(t.amount)}</b></td>
    <td><span class="pill ${t.status.toLowerCase()}">${esc(t.status)}</span></td>
    <td class="a">
     ${t.status==='PENDING'&&t.paymentMethod==='CASH_AT_SPA'?`<button class="btn s" data-a="confirm-cash" data-i="${esc(t.id)}" style="background:var(--forest);color:var(--iv)">Confirm Cash</button>`:''}
     ${t.status==='PAID'?`<button class="btn s" data-a="admin-rec" data-i="${esc(t.id)}">Receipt</button>`:''}
     ${t.status==='PAID'||t.status==='PARTIALLY_REFUNDED'?`<button class="btn s dg" data-a="open-refund-txn" data-i="${esc(t.id)}" data-b="${esc(t.rawBookingId||t.bookingId)}" data-amt="${t.amount}">Refund</button>`:''}
     ${t.status==='PAID'&&t.type==='DOWNPAYMENT'?`<button class="btn s dg" data-a="forfeit-txn" data-i="${esc(t.id)}" data-b="${esc(t.rawBookingId||t.bookingId)}" data-amt="${t.amount}">Forfeit</button>`:''}
    </td>
   </tr>`,
   s:flat(t),
   f:{status:t.status,method:t.paymentMethod}
  }));
  const gridHtml=grid(isMini?"pay-txns-mini":"pay-txns",{
   ph:"Search transaction reference, booking ID, customer...",
   head,
   empty:"No transactions recorded yet.",
   fl:[{k:"status",t:"Status",o:STATUS_TXN_OPT},{k:"method",t:"Method",o:METHOD_OPT}],
   items
  });
  return (isMini?'':`<div class="sim-alert">SIMULATED PAYMENT TOTALS — NOT ACTUAL REVENUE</div><div class="demo-banner">DEMO PAYMENT SYSTEM — NO REAL MONEY WILL BE TRANSFERRED. All records shown are local simulations.</div>`)+gridHtml;
 },
 "pay-sett"(){
  const s=DB.paymentSettings()||{};
  const dp=s.DOWNPAYMENT_PERCENTAGE||"25";
  const minDp=s.MIN_DOWNPAYMENT_AMOUNT||"500.00";
  const allowFull=s.ALLOW_FULL_PAYMENT!=="0";
  const demoActive=s.DEMO_MODE_ACTIVE!=="0";
  const forfeitLate=s.FORFEIT_LATE_CANCELLATION!=="0";
  return `
   <div class="demo-banner">DEMO PAYMENT RULES & CONFIGURATION</div>
   <div style="background:#fff;border:1px solid #c9bfa4;padding:24px;border-radius:4px;max-width:640px">
    <form id="pay-sett-form" novalidate>
     <div class="f">
      <label for="set-dp">Required Downpayment Percentage (%)</label>
      <input type="number" id="set-dp" min="1" max="100" value="${esc(dp)}" required>
      <small style="color:var(--mut)">Formula: Downpayment = Total Amount * (Percentage / 100). Default is 25%.</small>
     </div>
     <div class="f" style="margin-top:14px">
      <label for="set-mindp">Minimum Downpayment Amount (₱)</label>
      <input type="number" id="set-mindp" min="0" step="50" value="${esc(minDp)}" required>
     </div>
     <div class="f" style="margin-top:14px">
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
       <input type="checkbox" id="set-allowfull" ${allowFull?'checked':''}>
       <span>Allow customers to pay full amount in advance</span>
      </label>
     </div>
     <div class="f" style="margin-top:14px">
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
       <input type="checkbox" id="set-forfeit" ${forfeitLate?'checked':''}>
       <span>Enforce 24-hour late cancellation downpayment forfeiture</span>
      </label>
     </div>
     <div class="f" style="margin-top:14px">
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
       <input type="checkbox" id="set-demo" ${demoActive?'checked':''}>
       <span>Demo Mode Active (Always enabled)</span>
      </label>
     </div>
     <div class="dr" style="margin-top:20px">
      <button class="btn" type="submit">Save Payment Settings</button>
     </div>
    </form>
   </div>
  `;
 },
 services(){
   const tabNav=`<div style="display:flex;gap:10px;margin-bottom:18px;border-bottom:1px solid rgba(197,164,109,.3);padding-bottom:12px">
     <a href="#services" class="btn s on" style="text-decoration:none">Spa Treatments &amp; Durations</a>
     <a href="#wellness" class="btn s" style="text-decoration:none">Wellness Experiences</a>
   </div>`;
   return tabNav+list("services");
  },
  rooms(){
   const tabNav=`<div style="display:flex;gap:10px;margin-bottom:18px;border-bottom:1px solid rgba(197,164,109,.3);padding-bottom:12px">
     <a href="#res-rooms" class="btn s" style="text-decoration:none">Physical Spa Rooms Inventory</a>
     <a href="#rooms" class="btn s on" style="text-decoration:none">Website Accommodation Cards</a>
   </div>`;
   return tabNav+`<p class="demo">Website accommodation cards shown on the public Accommodations page (accommodations.html).</p>`+list("rooms");
  },
  wellness(){
   const tabNav=`<div style="display:flex;gap:10px;margin-bottom:18px;border-bottom:1px solid rgba(197,164,109,.3);padding-bottom:12px">
     <a href="#services" class="btn s" style="text-decoration:none">Spa Treatments &amp; Durations</a>
     <a href="#wellness" class="btn s on" style="text-decoration:none">Wellness Experiences</a>
   </div>`;
   return tabNav+`<p class="demo">Specialized wellness experiences shown on the public Wellness page (wellness.html).</p>`+list("wellness");
  },
  faqs:()=>list("faqs"),content:()=>list("content"),gallery:()=>list("gallery"),
 "b-res"(){const R=DB.reschedules(),pc=x=>x.status==="APPROVED"?"confirmed":x.status==="DECLINED"?"cancelled":x.status==="PENDING"?"pending":"inactive",tm=t=>t?t:"";
  return `<p class="demo">Customers ask to move a booking here. Approving moves it to the new time after the same double-booking check as any booking. Declining keeps the original time and tells the customer.</p>`+grid("b-res",{ph:"Search customer, service or booking ID",head:["Customer","Booking","Now","Requested","Reason","Status","Actions"],empty:"No reschedule requests yet.",
  fl:[{k:"status",t:"Status",o:[["","All requests"],["PENDING","Pending"],["APPROVED","Approved"],["DECLINED","Declined"],["WITHDRAWN","Withdrawn"]]}],
  items:R.map(x=>({h:`<tr><td>${esc(x.customer)}</td><td>${esc(x.booking)}<br><small>${esc(x.service)}</small></td><td>${when(x.currentDate)} ${esc(tm(x.currentTime))}</td><td><b>${when(x.date)} ${esc(x.time)}</b></td><td title="${esc(x.reason)}">${esc((x.reason||"—").length>80?x.reason.slice(0,80)+"…":(x.reason||"—"))}${x.adminNote?`<br><small>Note: ${esc(x.adminNote)}</small>`:""}</td><td><span class="pill ${pc(x)}">${esc(cap(x.status.toLowerCase()))}</span></td><td class="a">${x.status==="PENDING"?`<button class="btn s" data-a="rs" data-i="${x.id}" data-v="APPROVED">Approve</button><button class="btn s d" data-a="rs" data-i="${x.id}" data-v="DECLINED">Decline</button>`:""}</td></tr>`,s:flat(x),f:{status:x.status}}))})},
 reviews(){const R=DB.reviews(),st=n=>"★".repeat(n)+"☆".repeat(5-n),avg=R.filter(x=>!x.hidden).length?(R.filter(x=>!x.hidden).reduce((a,x)=>a+x.rating,0)/R.filter(x=>!x.hidden).length).toFixed(1):"—";
  return `<p class="demo">Reviews come from customers after a completed visit. Visible reviews appear in "Guest reviews" on the home page (first name and last initial only). Hiding a review removes it from the website; it is never deleted. Average of visible reviews: <b>${avg}</b></p>`+grid("reviews",{ph:"Search customer, service or comment",head:["Customer","Service","Rating","Comment","Date","Website","Actions"],empty:"No reviews yet. They appear after customers review a completed booking.",
  fl:[{k:"rating",t:"Rating",o:[["","All ratings"],["5","5 stars"],["4","4 stars"],["3","3 stars"],["2","2 stars"],["1","1 star"]]},{k:"shown",t:"Website",o:[["","Shown and hidden"],["yes","Shown"],["no","Hidden"]]}],
  items:R.map(x=>({h:`<tr><td>${esc(x.customer)}<br><small>${esc(x.booking)}</small></td><td>${esc(x.service)}</td><td title="${x.rating} of 5" style="white-space:nowrap;color:#8A6A28">${st(x.rating)}</td><td title="${esc(x.comment)}">${esc((x.comment||"—").length>140?x.comment.slice(0,140)+"…":(x.comment||"—"))}</td><td>${when(x.createdAt.slice(0,10))}</td><td><span class="pill ${x.hidden?"inactive":"confirmed"}">${x.hidden?"Hidden":"Shown"}</span></td><td class="a"><button class="btn s${x.hidden?"":" d"}" data-a="rv" data-i="${x.id}" data-v="${x.hidden?"0":"1"}">${x.hidden?"Show":"Hide"}</button></td></tr>`,s:flat(x),f:{rating:String(x.rating),shown:x.hidden?"no":"yes"}}))})},
 customers(){const c=cust();return `<p class="demo">Customers are never deleted. Deactivating signs them out and stops them logging in; their bookings and payment history are kept.</p>`+grid("customers",{ph:"Search name, email or phone",head:["Name","Email","Phone","Bookings","Last visit","Status","Actions"],empty:"Customers appear here when they register or are booked.",
  fl:[{k:"status",t:"Status",o:[["","All customers"],["Active","Active"],["Inactive","Inactive"],["Walk-in","Walk-in"]]}],
  items:c.map(x=>({h:`<tr><td>${esc(x.name)}</td><td>${esc(x.email||"—")}</td><td>${esc(x.phone||"—")}</td><td>${x.n}</td><td>${when(x.last)}</td><td>${x.reg?`<span class="pill ${x.active?"confirmed":"inactive"}">${x.active?"Active":"Inactive"}</span>`:`<span class="pill">Walk-in</span>`}</td><td class="a"><button class="btn s" data-a="cv" data-i="${esc(x.key)}">View</button>${x.reg?`<button class="btn s${x.active?" d":""}" data-a="act" data-i="${esc(x.key)}" data-v="${x.active?"0":"1"}">${x.active?"Deactivate":"Reactivate"}</button>`:""}</td></tr>`,s:flat(x),f:{status:x.reg?(x.active?"Active":"Inactive"):"Walk-in"}}))})},
 reports(){
   const b=bkAll();
   const revBookings=b.filter(x=>/Confirmed|Completed/.test(x.status)&&x.amount);
   const totalRev=revBookings.reduce((a,x)=>a+Number(x.amount||0),0);
   const avgTicket=revBookings.length ? Math.round(totalRev / revBookings.length) : 0;
   const m={};
   revBookings.forEach(x=>{const k=(x.date||"").slice(0,7)||"No date";m[k]=(m[k]||0)+Number(x.amount)});

   const by=(f)=>{const map={};b.forEach(x=>{const k=x[f]||"Unspecified";map[k]=(map[k]||0)+1});return Object.entries(map)};

   return `
     <p class="demo">Comprehensive operational reports and revenue analytics compiled from all booking records.</p>
     <div class="cards" style="margin-bottom:24px">
       <div class="card"><small>Total Bookings (All-Time)</small><b>${b.length}</b></div>
       <div class="card"><small>Confirmed &amp; Completed</small><b style="color:var(--forest)">${revBookings.length}</b></div>
       <div class="card"><small>Total Revenue (Entered)</small><b style="color:var(--forest)">${peso(totalRev)}</b></div>
       <div class="card"><small>Avg. Revenue / Booking</small><b>${peso(avgTicket)}</b></div>
     </div>

     <h2 class="sec" style="margin-top:0">Monthly Revenue Summary</h2>
     <p class="demo">Revenue sums amounts entered on confirmed or completed bookings.</p>
     ${revBookings.length?table(["Month","Revenue"],Object.entries(m).sort().map(([k,v])=>`<tr><td><b>${esc(k)}</b></td><td><b style="color:var(--forest)">${peso(v)}</b></td></tr>`).join("")):empty("No revenue recorded yet.")}

     <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:28px">
       <div>
         <h2 class="sec" style="margin-top:0">Bookings by Status</h2>
         ${table(["Status","Count"],by("status").map(([k,v])=>`<tr><td><span class="pill ${esc(k).toLowerCase()}">${esc(k)}</span></td><td><b>${v}</b></td></tr>`).join(""))}
       </div>
       <div>
         <h2 class="sec" style="margin-top:0">Bookings by Service</h2>
         ${table(["Service","Count"],by("service").map(([k,v])=>`<tr><td>${esc(k)}</td><td><b>${v}</b></td></tr>`).join(""))}
       </div>
     </div>
   `;
  },
  revenue(){ return V.reports(); },
 notif(){const p=bk().filter(x=>x.status==="Pending");return grid("notif",{list:1,ph:"Search pending bookings",empty:"You're all caught up. No pending bookings.",items:p.map(x=>({h:`<li>Pending booking from ${esc(x.name)}<small>${esc(x.service||"No service")} · ${when(x.date)}</small></li>`,s:flat(x),f:{}}))})},
 log(n){const all=DB.adminLog(),li=x=>`<li><b>${esc(x.admin||"Admin")}</b> — ${esc(x.action)}${x.target?" · "+esc(x.target):""}${x.detail?"<br>"+esc(x.detail):""}<small>${new Date(x.at).toLocaleString()}</small></li>`;
  if(n){const l=all.slice(0,n);return l.length?`<ul class="l">${l.map(li).join("")}</ul>`:empty("No activity yet.")}
  return grid("log",{list:1,ph:"Search activity",empty:"No activity yet.",items:all.map(x=>({h:li(x),s:flat(x),f:{action:x.action}})),fl:[{k:"action",t:"Action",o:[["","All actions"]].concat([...new Set(all.map(x=>x.action))].sort().map(a=>[a,a]))}]})},
 settings:()=>`<p class="demo">Signed in as ${esc(U.fullName)} (${esc(U.email)}) \u00B7 Administrator. The admin role is stored in the database (users.role).</p><h2 class="sec" style="margin-top:0">Security check</h2><ul class="l">${DB.checks().map(c=>`<li><span class="pill ${c.ok?"confirmed":"cancelled"}">${c.ok?"OK":"To do"}</span> <b>${esc(c.title)}</b><br>${esc(c.fix)}</li>`).join("")}</ul><h2 class="sec">Change password</h2><form id="apw" novalidate style="max-width:360px"><div class="f"><label for="pc">Current password</label><input id="pc" type="password" autocomplete="current-password"></div><div class="f"><label for="pn">New password</label><input id="pn" type="password" autocomplete="new-password"></div><div class="f"><label for="pf">Confirm new password</label><input id="pf" type="password" autocomplete="new-password"></div><button class="btn" type="submit">Update password</button><p id="apwm" role="status" style="font-size:.85rem;margin-top:10px"></p></form>`};
function cust(){const m={};
 DB.customers().filter(c=>c.role!=="ADMIN").forEach(c=>{m[c.id]={key:c.id,reg:true,active:c.active!==false,name:c.fullName,email:c.email,phone:c.phone,n:0,last:""}});
 bkAll().forEach(x=>{const k=x.customerId||(x.email||x.name||"").toLowerCase();if(!k)return;const c=m[k]||(m[k]={key:k,reg:false,active:true,name:x.name,email:x.email,phone:x.phone,n:0,last:""});c.n++;if((x.date||"")>c.last)c.last=x.date});return Object.values(m)}
function custView(key){const cu=DB.customers().find(c=>c.id===key||c.email===key),bs=DB.allBookings().concat(DB.archivedBookings()).filter(b=>(b.customerId||(b.email||b.name||"").toLowerCase())===key).sort((a,b)=>(b.date||"").localeCompare(a.date||"")),ts=cu?DB.allTxns().filter(t=>t.customerId===cu.id):[],n=s=>bs.filter(b=>b.status===s).length,sp=ts.filter(t=>t.status==="PAID").reduce((a,t)=>a+t.amount,0),f=bs[0]||{};
 $("#cvb").innerHTML=`<p><b>${esc(cu?cu.fullName:f.name)}</b><br>${esc(cu?cu.email:f.email||"No email")}<br><small>${cu?"Member since "+when(cu.createdAt.slice(0,10)):"Walk-in / not a registered member"}</small></p><div class="cards" style="margin:14px 0"><div class="card"><small>Total bookings</small><b>${bs.length}</b></div><div class="card"><small>Completed</small><b>${n("COMPLETED")}</b></div><div class="card"><small>Cancelled</small><b>${n("CANCELLED")}</b></div><div class="card"><small>Transactions</small><b>${ts.length}</b></div><div class="card"><small>Total spending</small><b>${peso(sp)}</b></div></div>
 <h3 class="sec" style="margin-top:6px">Booking history</h3>${bs.length?`<ul class="l">${bs.map(b=>`<li>${esc(b.serviceName)} <span class="pill ${b.status.toLowerCase()}">${b.status}</span><small>${when(b.date)} \u00B7 ${esc(b.id)}${b.cancelReason?" \u00B7 "+esc(b.cancelReason):""}</small></li>`).join("")}</ul>`:empty("No bookings.")}
 <h3 class="sec">Transaction history</h3>${ts.length?`<ul class="l">${ts.map(t=>`<li>${esc(t.id)} \u00B7 ${esc(t.description)} \u00B7 ${peso(t.amount)} <span class="pill ${t.status.toLowerCase()}">${t.status}</span><small>${esc(t.bookingId)}</small></li>`).join("")}</ul>`:empty("No transactions.")}
 <h3 class="sec">Activity timeline</h3>${cu&&DB.activity(cu.id).length?`<ul class="l">${DB.activity(cu.id).slice(0,10).map(a=>`<li>${esc(a.text)}<small>${new Date(a.at).toLocaleString()}</small></li>`).join("")}</ul>`:empty("No activity.")}`;$("#cvd").showModal()}
function show(){
 const v=(location.hash||"#dash").slice(1);
 const k=V[v]?v:"dash";
 let navKey=k;
 if(k==="rooms") navKey="res-rooms";
 if(k==="wellness") navKey="services";
 if(k==="revenue") navKey="reports";

 $("#ttl").textContent=TT[k]||TT[navKey]||"Dashboard";
 $("#ct").innerHTML=V[k]();
 $("#ct").style.animation="none";
 $("#ct").offsetWidth;
 $("#ct").style.animation="";

 document.querySelectorAll(".nav-link, .nav-dash").forEach(a=>{
  const isTarget=a.getAttribute("href")==="#"+navKey;
  a.classList.toggle("on",isTarget);
  if(isTarget){
   const grp=a.closest(".nav-group");
   if(grp){
    grp.classList.remove("collapsed");
    const hdr=grp.querySelector(".nav-group-header");
    if(hdr) hdr.setAttribute("aria-expanded","true");
   }
  }
 });

 $("#sb").classList.remove("open");
 const bd=document.getElementById("sb-backdrop");
 if(bd) bd.classList.remove("open");
 $("#bg").setAttribute("aria-expanded","false");
}
addEventListener("hashchange",show);show();

/* ---------- Unsaved changes value-snapshot tracking & discard modal ---------- */
let activeForm = null, activeSnapshot = "";

function serializeForm(form){
 if(!form) return "";
 const els = Array.from(form.querySelectorAll("input, select, textarea"));
 return els.map(el => {
  if(el.type === "checkbox" || el.type === "radio") return (el.name||el.id)+":"+el.checked;
  return (el.name||el.id)+":"+el.value;
 }).join("||");
}

function trackForm(form){
 if(!form) return;
 activeForm = form;
 activeSnapshot = serializeForm(form);
}

function clearDirty(){
 activeForm = null;
 activeSnapshot = "";
}

function hasUnsavedChanges(){
 if(!activeForm || !activeForm.isConnected) return false;
 return serializeForm(activeForm) !== activeSnapshot;
}

async function confirmDiscard(){
 if(!hasUnsavedChanges()){
  clearDirty();
  return true;
 }
 const discard = await Notice.confirm({
  title: "Unsaved Changes",
  message: "You have unsaved changes. Do you want to leave without saving?",
  confirmLabel: "Discard Changes",
  cancelLabel: "Keep Editing",
  danger: true
 });
 if(discard){
  clearDirty();
  return true;
 }
 return false;
}

document.addEventListener("input", e=>{
 const f = e.target.closest("#df, #asgn-form, #maint-form, #clean-form, #refund-form, #pay-sett-form, #apw");
 if(f && f !== activeForm){
  trackForm(f);
 }
});
document.addEventListener("change", e=>{
 const f = e.target.closest("#df, #asgn-form, #maint-form, #clean-form, #refund-form, #pay-sett-form, #apw");
 if(f && f !== activeForm){
  trackForm(f);
 }
});

window.addEventListener("beforeunload", e=>{
 if(hasUnsavedChanges()){
  e.preventDefault();
  e.returnValue = "";
 }
});

document.addEventListener("click", async e=>{
 const link = e.target.closest("#sb a");
 if(link && hasUnsavedChanges()){
  e.preventDefault();
  const href = link.getAttribute("href");
  const discard = await confirmDiscard();
  if(discard){
   location.hash = href;
  }
 }
}, true);

["#dlg", "#asgn-dlg", "#maint-dlg", "#clean-dlg", "#refund-dlg"].forEach(sel=>{
 const d = document.querySelector(sel);
 if(d){
  d.addEventListener("cancel", async e=>{
   if(hasUnsavedChanges()){
    e.preventDefault();
    if(await confirmDiscard()) d.close();
   }
  });
 }
});

/* add / edit form */
let cur=null;
function form(k,id){clearDirty();const s=SC[k],r=id?ld(k).find(x=>x.id===id):{status:"Pending",date:day(new Date())};cur={k,id};$("#dt").textContent=(id?"Edit ":"Add ")+s.t.toLowerCase();
 $("#fl").innerHTML=s.f.map(([n,l,t,o])=>`<div class="f"><label for="f_${n}">${l}</label>${t==="textarea"?`<textarea id="f_${n}" rows="3">${esc(r[n]||"")}</textarea>`:t==="select"?`<select id="f_${n}">${o.map(x=>`<option${r[n]===x?" selected":""}>${x}</option>`).join("")}</select>`:`<input id="f_${n}" type="${t||"text"}" value="${esc(r[n]||"")}"${t==="number"?' min="0"':""}>`}</div>`).join("");
 const ph=$("#f_phone");if(ph)formatPhoneInput(ph);
 trackForm($("#df"));
 $("#dlg").showModal();$("#fl input,#fl select,#fl textarea").focus()}
$("#df").addEventListener("submit",async e=>{e.preventDefault();const {k,id}=cur,s=SC[k],d={};s.f.forEach(([n])=>d[n]=$("#f_"+n).value.trim());
 if(!d[s.f[0][0]]){$("#f_"+s.f[0][0]).focus();return}
 if(d.phone){
  if(!isValidPhone(d.phone)){
   Notice.warning("Invalid Phone Number", "Phone number must contain 11 digits (09…) or 12 digits (639…).");
   const el=$("#f_phone");if(el)el.focus();
   return;
  }
  d.phone=formatPhone(d.phone);
 }
 if(k==="bookings"){const r=await DB.saveBooking({...d,status:d.status.toUpperCase()},id);if(!r.ok){Notice.error("Save Booking Failed", r.data.error||"Could not save the booking.");return}await log((id?"Edited":"Added")+" booking: "+d.name);$("#dlg").close();show();return}
 if(k==="res-rooms"){const r=await DB.saveRoom(d,id);if(!r.ok){Notice.error("Save Room Failed", r.data.error||"Could not save room.");return}await DB.loadAdmin();$("#dlg").close();show();return}
 if(k==="res-pkgs"){const r=await DB.savePackage(d,id);if(!r.ok){Notice.error("Save Package Failed", r.data.error||"Could not save package.");return}await DB.loadAdmin();$("#dlg").close();show();return}
 if(k==="res-th"){const r=await DB.saveTherapist(d,id);if(!r.ok){Notice.error("Save Therapist Failed", r.data.error||"Could not save therapist.");return}await DB.loadAdmin();$("#dlg").close();show();return}
 const a=ld(k);if(k==="content"&&!id&&a.some(x=>x.section===d.section)){Notice.warning("Section Exists", "That section already has a block. Use Edit on it instead.");return}
 if(id){Object.assign(a.find(x=>x.id===id),d)}else{a.unshift({id:Date.now().toString(36),...d})}
 const r=await sv(k,a);if(!r.ok)return;clearDirty();await log(`${id?"Edited":"Added"} ${s.t.toLowerCase()}: ${d[s.f[0][0]]}`);$("#dlg").close();show()});
$("#dc").onclick=async()=>{if(await confirmDiscard())$("#dlg").close();};

/* Resource Dialog Handlers */
$("#asgn-form").addEventListener("submit",async e=>{
  e.preventDefault();
  const bkId=$("#asgn_bkid").value;
  const roomId=$("#asgn_r").value;
  const thId=$("#asgn_t").value;
  if(!roomId&&!thId){Notice.warning("Assignment Required", "Please select a room or therapist to assign.");return}
  const rObj = DB.rooms().find(r=>String(r.id)===String(roomId));
  const thObj = DB.therapists().find(t=>String(t.id)===String(thId));
  const ok = await Notice.confirm({
   title: "Confirm Room & Therapist Assignment?",
   message: "Save resource assignment for Booking #"+bkId+"?" + (rObj?"\n• Suite: "+esc(rObj.room_code)+" ("+esc(rObj.room_name)+")":"") + (thObj?"\n• Therapist: "+esc(thObj.employee_code)+" ("+esc(thObj.full_name)+")":""),
   confirmLabel: "Assign Resources",
   cancelLabel: "Cancel"
  });
  if(!ok) return;
  if(roomId){
    const r=await DB.assignRoom(bkId,roomId);
    if(!r.ok){Notice.error("Room Assignment Failed", r.data.error||"Could not assign room.");return}
  }
  if(thId){
    const r=await DB.assignTherapist(bkId,thId);
    if(!r.ok){Notice.error("Therapist Assignment Failed", r.data.error||"Could not assign therapist.");return}
  }
  Notice.toast("success", "Room & therapist assigned successfully.");
  clearDirty();await DB.loadAdmin();
  $("#asgn-dlg").close();
  show();
});

$("#maint-form").addEventListener("submit",async e=>{
  e.preventDefault();
  const rid=$("#m_rid").value;
  const r=DB.rooms().find(x=>String(x.id)===String(rid));
  const sDate=$("#m_sdate").value, sTime=$("#m_stime").value, eDate=$("#m_edate").value, eTime=$("#m_etime").value, reason=$("#m_reason").value;
  const ok = await Notice.confirm({
   title: "Schedule Room Maintenance?",
   message: "Place "+esc(r?r.room_name:"Room")+" ("+esc(r?r.room_code:"")+") into MAINTENANCE from "+sDate+" "+sTime+" to "+eDate+" "+eTime+"? The room will be unavailable for customer appointments.",
   confirmLabel: "Confirm Maintenance",
   cancelLabel: "Cancel",
   danger: true
  });
  if(!ok) return;
  const res=await DB.saveRoomMaintenance(rid,{
    startDate:sDate,
    startTime:sTime,
    endDate:eDate,
    endTime:eTime,
    reason:reason
  });
  if(!res.ok){Notice.error("Maintenance Schedule Failed", res.data.error||"Could not schedule maintenance.");return}
  Notice.toast("success", "Room maintenance scheduled.");
  clearDirty();await DB.loadAdmin();
  $("#maint-dlg").close();
  show();
});

$("#clean-form").addEventListener("submit",async e=>{
  e.preventDefault();
  const cid=$("#cl_id").value;
  const clSt=$("#cl_st").value, clBy=$("#cl_by").value, clNote=$("#cl_note").value;
  const ok = await Notice.confirm({
   title: "Update Cleaning Status?",
   message: "Update cleaning task #"+cid+" to status \""+clSt+"\""+(clBy?" (Staff: "+esc(clBy)+")":"")+"?",
   confirmLabel: "Update Status",
   cancelLabel: "Cancel"
  });
  if(!ok) return;
  const res=await DB.setCleaningStatus(cid,clSt,clBy,clNote);
  if(!res.ok){Notice.error("Cleaning Update Failed", res.data.error||"Could not update cleaning status.");return}
  Notice.toast("success", "Cleaning status updated.");
  clearDirty();await DB.loadAdmin();
  $("#clean-dlg").close();
  show();
});

function renderTherapistSched(t,schedData){
  const shifts=schedData?.shifts||[];
  const breaks=schedData?.breaks||[];
  const leaves=schedData?.leaves||[];
  const dayNames=["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
  let shRows="";
  for(let i=1;i<=6;i++){
    const s=shifts.find(x=>Number(x.day_of_week)===i);
    shRows+=`<tr><td>${dayNames[i]}</td><td>${s?esc(s.shift_start):"13:00"}</td><td>${s?esc(s.shift_end):"21:00"}</td><td>${s&&!Number(s.is_working_day)?"<span class='pill inactive'>Off</span>":"<span class='pill confirmed'>Working</span>"}</td></tr>`;
  }
  const s0=shifts.find(x=>Number(x.day_of_week)===0);
  shRows+=`<tr><td>Sun</td><td>${s0?esc(s0.shift_start):"13:00"}</td><td>${s0?esc(s0.shift_end):"21:00"}</td><td>${s0&&!Number(s0.is_working_day)?"<span class='pill inactive'>Off</span>":"<span class='pill confirmed'>Working</span>"}</td></tr>`;

  $("#sched-body").innerHTML=`
    <p><b>${esc(t.employee_code)}: ${esc(t.full_name)}</b> · ${esc(t.specialization||"All Services")}<br>
    Status: <span class="pill ${t.employment_status.toLowerCase()}">${esc(t.employment_status)}</span> <span class="pill ${t.availability_status.toLowerCase()}">${esc(t.availability_status)}</span></p>
    <h4 style="margin:16px 0 8px">Weekly Shift Schedule (1:00 PM – 9:00 PM)</h4>
    <div class="tw"><table><thead><tr><th>Day</th><th>Start</th><th>End</th><th>Status</th></tr></thead><tbody>${shRows}</tbody></table></div>

    <h4 style="margin:20px 0 8px">Breaks</h4>
    ${breaks.length?`<ul class="l">${breaks.map(b=>`<li><b>${esc(b.break_type)}</b>: ${esc(b.start_time)} – ${esc(b.end_time)}${b.break_date?" on "+when(b.break_date):" (Daily)"}${b.notes?" · <small>"+esc(b.notes)+"</small>":""}</li>`).join("")}</ul>`:empty("No active breaks.")}
    <div style="background:#fcfaf5;padding:12px;border:1px solid #e0d6be;margin-top:10px">
      <small style="font-weight:600;text-transform:uppercase;color:var(--forest)">Add Break</small>
      <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
        <input type="date" id="brk_date" style="padding:6px;font:inherit">
        <input type="time" id="brk_start" value="17:00" style="padding:6px;font:inherit">
        <input type="time" id="brk_end" value="17:30" style="padding:6px;font:inherit">
        <input type="text" id="brk_type" placeholder="Break type (e.g. Meal Break)" value="Meal Break" style="padding:6px;font:inherit;flex:1">
        <button class="btn s" type="button" data-a="addbreak" data-i="${t.id}">Add Break</button>
      </div>
    </div>

    <h4 style="margin:20px 0 8px">Leaves</h4>
    ${leaves.length?`<ul class="l">${leaves.map(l=>`<li><b>${esc(l.leave_type)}</b>: ${when(l.start_date)} – ${when(l.end_date)} <span class="pill ${l.status.toLowerCase()}">${esc(l.status)}</span>${l.reason?"<br><small>"+esc(l.reason)+"</small>":""}</li>`).join("")}</ul>`:empty("No leaves recorded.")}
    <div style="background:#fcfaf5;padding:12px;border:1px solid #e0d6be;margin-top:10px">
      <small style="font-weight:600;text-transform:uppercase;color:var(--forest)">Record Leave</small>
      <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
        <input type="date" id="lv_start" style="padding:6px;font:inherit">
        <input type="date" id="lv_end" style="padding:6px;font:inherit">
        <input type="text" id="lv_type" placeholder="Leave type (e.g. Annual Leave)" value="Annual Leave" style="padding:6px;font:inherit">
        <input type="text" id="lv_reason" placeholder="Reason" style="padding:6px;font:inherit;flex:1">
        <button class="btn s" type="button" data-a="addleave" data-i="${t.id}">Record Leave</button>
      </div>
    </div>
  `;
}

document.addEventListener("click",async e=>{const b=e.target.closest("[data-a]");if(!b)return;const {a,k,i,v}=b.dataset;
 if(a==="add")form(k);else if(a==="edit")form(k,i);
 else if(a==="del"){
  const bkg=k==="bookings";
  if(bkg){
   const item = bkAll().find(x=>String(x.id)===String(i)) || {};
   const ok=await Notice.confirm({
    title: "Archive Booking?",
    message: "This booking will be removed from the active booking list. Its booking and payment history will be preserved, and it can be restored from Archived Bookings.",
    details: [
      ["Booking Reference", "#" + (item.id || i)],
      ["Customer", item.name || "—"],
      ["Treatment", item.serviceName || item.service || "—"],
      ["Date & Time", when(item.date) + " at " + (item.time || "—")]
    ],
    confirmLabel: "Archive Booking",
    cancelLabel: "Keep Booking",
    danger: true
   });
   if(!ok) return;
   const r = await DB.deleteBooking(i);
   if(!r.ok){ Notice.error("Archive Failed", r.data.error || "Could not archive booking."); return; }
   Notice.toast("success", "Booking archived successfully.");
   await DB.loadAdmin();
   show();
  } else {
   const items = ld(k);
   const item = items.find(x=>x.id===i) || {};
   const recName = item.name || item.title || item.q || item.section || ("#" + i);
   const catName = SC[k] ? SC[k].t : "Record";
   const ok=await Notice.confirm({
    title: "Delete this record?",
    message: "You are about to delete the selected record. Are you sure you want to continue?",
    details: [
      ["Record Name", recName],
      ["Record ID", String(i)],
      ["Category", catName]
    ],
    confirmLabel: "Delete",
    cancelLabel: "Cancel",
    danger: true
   });
   if(!ok) return;
   const r = await sv(k, items.filter(x=>x.id!==i));
   if(!r.ok){ Notice.error("Delete Failed", r.data.error || "Could not delete record."); return; }
   Notice.toast("success", catName + " deleted successfully.");
   show();
  }
 }
 else if(a==="st"){
  const item = bkAll().find(x=>String(x.id)===String(i)) || { name: "Guest", service: "Spa Service", date: "", time: "" };
  let reason="";
  if(v==="Cancelled"){
   const paidAmt = Number(item.paidAmount || 0);
   reason=await Notice.prompt({
    title:"Cancel Appointment?",
    message:"Are you sure you want to cancel this booking? This may affect its payment and reservation status.",
    details: [
      ["Booking Reference", "#" + item.id],
      ["Customer Name", item.name || "—"],
      ["Treatment", item.serviceName || item.service || "—"],
      ["Appointment Date", when(item.date)],
      ["Appointment Time", item.time || "—"],
      ["Assigned Suite", item.roomName || "Unassigned"],
      ["Guest Count", (item.guests || 1) + " guest(s)"],
      ["Booking Status", item.status || "—"],
      ["Payment Status", item.paymentStatus || "UNPAID"],
      ["Amount Paid", peso(paidAmt)]
    ],
    inputLabel:"Please provide cancellation reason (required):",
    defaultValue:"Admin cancelled",
    confirmLabel:"Cancel Booking",
    cancelLabel:"Go Back",
    required:true,
    requiredMessage:"Cancellation reason is required for the audit log.",
    danger:true
   });
   if(reason===null)return;
  }else if(v==="Rejected"){
   reason=await Notice.prompt({
    title:"Reject Booking?",
    message:"Are you sure you want to reject this appointment request?",
    details: [
      ["Booking Reference", "#" + item.id],
      ["Customer", item.name || "—"],
      ["Treatment", item.serviceName || item.service || "—"],
      ["Date & Time", when(item.date) + " at " + (item.time || "—")],
      ["Guests", (item.guests || 1) + " guest(s)"]
    ],
    inputLabel:"Reason for rejection (optional):",
    defaultValue:"Schedule conflict",
    confirmLabel:"Reject Booking",
    cancelLabel:"Cancel",
    danger:true
   });
   if(reason===null)return;
  }else if(v==="No-show"){
   const ok=await Notice.confirm({
    title:"Mark as No-Show?",
    message:"Mark this booking as No-show? The customer will be recorded as absent.",
    details: [
      ["Booking Reference", "#" + item.id],
      ["Customer", item.name || "—"],
      ["Appointment Schedule", when(item.date) + " at " + (item.time || "—")]
    ],
    confirmLabel:"Confirm No-Show",
    cancelLabel:"Cancel",
    danger:true
   });
   if(!ok)return;
  }else if(v==="Confirmed"){
   const ok=await Notice.confirm({
    title:"Confirm Booking?",
    message:"Approve and confirm this appointment reservation?",
    details: [
      ["Booking Reference", "#" + item.id],
      ["Customer", item.name || "—"],
      ["Treatment", item.serviceName || item.service || "—"],
      ["Appointment Schedule", when(item.date) + " at " + (item.time || "—")],
      ["Duration", (item.duration || 60) + " mins"],
      ["Guests", (item.guests || 1) + " guest(s)"]
    ],
    confirmLabel:"Approve Booking",
    cancelLabel:"Cancel"
   });
   if(!ok)return;
  }else if(v==="In_progress"){
   const ok=await Notice.confirm({
    title:"Start Treatment?",
    message:"Mark this appointment as In Progress? The customer has arrived and treatment has begun.",
    details: [
      ["Booking Reference", "#" + item.id],
      ["Customer", item.name || "—"],
      ["Treatment", item.serviceName || item.service || "—"],
      ["Assigned Suite", item.roomName || "Unassigned"],
      ["Assigned Therapist", item.therapistName || "Unassigned"]
    ],
    confirmLabel:"Start Treatment",
    cancelLabel:"Cancel"
   });
   if(!ok)return;
  }else if(v==="Completed"){
   const ok=await Notice.confirm({
    title:"Complete Appointment?",
    message:"Mark this appointment as Completed? This finishes the session and logs the mandatory 30-minute cleaning buffer.",
    details: [
      ["Booking Reference", "#" + item.id],
      ["Customer", item.name || "—"],
      ["Treatment", item.serviceName || item.service || "—"],
      ["Payment Status", item.paymentStatus || "UNPAID"]
    ],
    confirmLabel:"Complete Appointment",
    cancelLabel:"Cancel"
   });
   if(!ok)return;
  }
  const r=await DB.setStatus(i,v.toUpperCase(),reason);
  if(!r.ok){Notice.error("Status Update Failed",r.data.error||"Could not update the booking.");return}
  Notice.toast("success",`Booking marked as ${v}.`);
  show();
 }
 else if(a==="asgn"){
  const bkList=bkAll();
  const item=bkList.find(x=>String(x.id)===String(i));
  if(!item)return;
  const rooms=DB.rooms().filter(r=>r.room_status!=="INACTIVE");
  const ths=DB.therapists().filter(t=>t.employment_status==="ACTIVE");
  $("#asgn-body").innerHTML=`
    <p><b>Booking #${esc(item.id)}: ${esc(item.name)}</b><br>
    Service: <b>${esc(item.serviceName||item.service)}</b> (${item.duration} mins) · Guests: <b>${item.guests||1}</b><br>
    Date: <b>${when(item.date)}</b> at <b>${esc(item.time)}</b> (Cleaning buffer ends: <b>${esc(item.cleaningEnd||"—")}</b>)</p>
    <div class="f">
      <label for="asgn_r">Assign Room</label>
      <select id="asgn_r">
        <option value="">-- Keep Current (${esc(item.roomName||"None")}) --</option>
        ${rooms.map(r=>`<option value="${r.id}"${(item.roomName&&(item.roomName===r.room_name||item.roomName===r.room_code))?" selected":""}>${esc(r.room_code)} — ${esc(r.room_name)} (${esc(r.category_name||r.category_code)}, max ${r.max_capacity} guests)</option>`).join("")}
      </select>
    </div>
    <div class="f">
      <label for="asgn_t">Assign Therapist</label>
      <select id="asgn_t">
        <option value="">-- Keep Current (${esc(item.therapistName||"None")}) --</option>
        ${ths.map(t=>`<option value="${t.id}"${(item.therapistName&&item.therapistName===t.full_name)?" selected":""}>${esc(t.employee_code)} — ${esc(t.full_name)} (${esc(t.specialization)})</option>`).join("")}
      </select>
    </div>
    <input type="hidden" id="asgn_bkid" value="${esc(item.id)}">
  `;
  trackForm($("#asgn-form"));$("#asgn-dlg").showModal();
 }
 else if(a==="maint"){
  const r=DB.rooms().find(x=>String(x.id)===String(i));
  if(!r)return;
  const td=day(new Date());
  $("#maint-body").innerHTML=`
    <p>Schedule maintenance downtime for <b>${esc(r.room_code)} — ${esc(r.room_name)}</b>.</p>
    <div class="f"><label for="m_sdate">Start Date</label><input type="date" id="m_sdate" value="${td}" required></div>
    <div class="f"><label for="m_stime">Start Time</label><input type="time" id="m_stime" value="13:00" required></div>
    <div class="f"><label for="m_edate">End Date</label><input type="date" id="m_edate" value="${td}" required></div>
    <div class="f"><label for="m_etime">End Time</label><input type="time" id="m_etime" value="21:00" required></div>
    <div class="f"><label for="m_reason">Reason</label><textarea id="m_reason" rows="2">Scheduled maintenance & sanitation</textarea></div>
    <input type="hidden" id="m_rid" value="${r.id}">
  `;
  trackForm($("#maint-form"));$("#maint-dlg").showModal();
 }
 else if(a==="deact-room"){
  const r=DB.rooms().find(x=>String(x.id)===String(i));
  if(!r)return;
  const act=v==="AVAILABLE";
  const ok=await Notice.confirm({
   title:act?"Reactivate Room":"Deactivate Room",
   message:act?`Reactivate room ${r.room_code}? It will become available for new bookings.`:`Deactivate room ${r.room_code}? Historical bookings will be preserved.`,
   confirmLabel:act?"Reactivate":"Deactivate",
   cancelLabel:"Cancel",
   danger:!act
  });
  if(!ok)return;
  const res=act?await DB.saveRoom({...r,status:"AVAILABLE"},r.id):await DB.deactivateRoom(r.id);
  if(!res.ok){Notice.error("Update Room Failed",res.data.error||"Could not update room.");return}
  Notice.toast("success",act?`Room ${r.room_code} reactivated.`:`Room ${r.room_code} deactivated.`);
  await DB.loadAdmin();
  show();
 }
 else if(a==="upd-clean"){
  const c=DB.cleanings().find(x=>String(x.id)===String(i));
  if(!c)return;
  $("#clean-body").innerHTML=`
    <p>Update cleaning log for <b>${esc(c.room_code||"Room "+c.room_id)}</b> (Booking #${c.booking_id})<br>
    Scheduled buffer: <b>${esc(c.treatment_end_time)} – ${esc(c.cleaning_end_time)}</b></p>
    <div class="f">
      <label for="cl_st">Cleaning Status</label>
      <select id="cl_st">
        <option value="PENDING"${c.cleaning_status==="PENDING"?" selected":""}>PENDING</option>
        <option value="IN_PROGRESS"${c.cleaning_status==="IN_PROGRESS"?" selected":""}>IN_PROGRESS</option>
        <option value="COMPLETED"${c.cleaning_status==="COMPLETED"?" selected":""}>COMPLETED</option>
      </select>
    </div>
    <div class="f"><label for="cl_by">Cleaned By</label><input type="text" id="cl_by" value="${esc(c.cleaned_by||U.fullName)}"></div>
    <div class="f"><label for="cl_note">Notes</label><input type="text" id="cl_note" value="${esc(c.cleaning_notes||"")}" placeholder="Optional notes"></div>
    <input type="hidden" id="cl_id" value="${c.id}">
  `;
  trackForm($("#clean-form"));$("#clean-dlg").showModal();
 }
 else if(a==="sched"){
  const t=DB.therapists().find(x=>String(x.id)===String(i));
  if(!t)return;
  const resp=await fetch("/api/admin/therapists/"+encodeURIComponent(t.id)+"/schedule",{credentials:"include"});
  const dt=await resp.json();
  renderTherapistSched(t,dt.schedule);
  $("#sched-dlg").showModal();
 }
 else if(a==="addbreak"){
  const tid=i;
  const t=DB.therapists().find(x=>String(x.id)===String(tid));
  const bStart=$("#brk_start").value, bEnd=$("#brk_end").value, bType=$("#brk_type").value||"Break";
  const ok = await Notice.confirm({
   title: "Add Therapist Break?",
   message: "Add "+bType+" ("+bStart+" – "+bEnd+") for "+esc(t?t.full_name:"therapist")+"?",
   confirmLabel: "Add Break",
   cancelLabel: "Cancel"
  });
  if(!ok) return;
  const res=await DB.addTherapistBreak(tid,{
    date:$("#brk_date").value||null,
    start:bStart,
    end:bEnd,
    type:bType
  });
  if(!res.ok){Notice.error("Add Break Failed",res.data.error||"Could not add break.");return}
  Notice.toast("success", "Therapist break added.");
  renderTherapistSched(t,res.data.schedule);
 }
 else if(a==="addleave"){
  const tid=i;
  const t=DB.therapists().find(x=>String(x.id)===String(tid));
  const lStart=$("#lv_start").value, lEnd=$("#lv_end").value, lType=$("#lv_type").value||"Leave";
  const ok = await Notice.confirm({
   title: "Record Therapist Leave?",
   message: "Record "+lType+" for "+esc(t?t.full_name:"therapist")+" from "+when(lStart)+" to "+when(lEnd)+"? The therapist will be unavailable for appointments during these dates.",
   confirmLabel: "Record Leave",
   cancelLabel: "Cancel",
   danger: true
  });
  if(!ok) return;
  const res=await DB.addTherapistLeave(tid,{
    startDate:lStart,
    endDate:lEnd,
    type:lType,
    notes:$("#lv_reason").value
  });
  if(!res.ok){Notice.error("Record Leave Failed",res.data.error||"Could not record leave.");return}
  Notice.toast("success", "Therapist leave recorded.");
  renderTherapistSched(t,res.data.schedule);
 }
 else if(a==="act"){
  const on=v==="1";
  const ok=await Notice.confirm({
   title:on?"Reactivate Customer":"Deactivate Customer",
   message:on?"Reactivate this customer? They will be able to log in again.":"Deactivate this customer? They will be signed out and cannot log in. Their bookings and history are kept.",
   confirmLabel:on?"Reactivate":"Deactivate",
   cancelLabel:"Cancel",
   danger:!on
  });
  if(!ok)return;
  const r=await DB.setCustomerActive(i,on);
  if(!r.ok){Notice.error("Update Customer Failed",r.data.error||"Could not update the customer.");return}
  Notice.toast("success",on?"Customer reactivated.":"Customer deactivated.");
  show();
 }
 else if(a==="restore"){
  const ab = DB.archivedBookings().find(x=>String(x.id)===String(i)) || {};
  const ok=await Notice.confirm({
   title:"Restore Archived Booking?",
   message:"Restore this archived booking back to active records?",
   details: [
     ["Booking Reference", "#" + (ab.id || i)],
     ["Customer", ab.name || "—"],
     ["Treatment", ab.serviceName || "—"],
     ["Date", when(ab.date) || "—"]
   ],
   confirmLabel:"Restore Booking",
   cancelLabel:"Cancel"
  });
  if(!ok)return;
  const r=await DB.restoreBooking(i);
  if(!r.ok){Notice.error("Restore Failed",r.data.error||"Could not restore.");return}
  Notice.toast("success","Booking restored successfully.");
  await DB.loadAdmin();
  show();
 }
 else if(a==="cv")custView(i);
 else if(a==="rs"){
  const rr = DB.reschedules().find(x=>String(x.id)===String(i));
  let note="";
  if(v==="ACCEPTED"){
   const ok = await Notice.confirm({
    title: "Approve Reschedule?",
    message: "Approve appointment reschedule request?",
    details: [
      ["Customer", rr ? rr.name : "Customer"],
      ["Original Schedule", rr ? (when(rr.currentDate) + " at " + rr.currentTime) : "Current"],
      ["Proposed Schedule", rr ? (when(rr.requestedDate) + " at " + rr.requestedTime) : "New Schedule"],
      ["Customer Reason", rr ? (rr.reason || "Customer request") : "—"]
    ],
    confirmLabel: "Approve Request",
    cancelLabel: "Cancel"
   });
   if(!ok) return;
  } else if(v==="DECLINED"){
   note=await Notice.prompt({
    title:"Reject Reschedule?",
    message:"Decline this appointment reschedule request?",
    details: [
      ["Customer", rr ? rr.name : "Customer"],
      ["Original Schedule", rr ? (when(rr.currentDate) + " at " + rr.currentTime) : "Current"],
      ["Proposed Schedule", rr ? (when(rr.requestedDate) + " at " + rr.requestedTime) : "New Schedule"]
    ],
    inputLabel:"Explanation note to the customer (why it cannot be moved):",
    defaultValue:"Requested time slot unavailable",
    confirmLabel:"Reject Request",
    cancelLabel:"Cancel",
    danger:true
   });
   if(note===null)return;
  }
  const r=await DB.decideReschedule(i,v,note||"");
  if(!r.ok){Notice.error("Reschedule Decision Failed",r.data.error||"Could not save the decision.");return}
  Notice.toast("success",v==="ACCEPTED"?"Reschedule request approved.":"Reschedule request declined.");
  show();
 }
 else if(a==="rv"){
  const hide = v === "1";
  const ok = await Notice.confirm({
   title: hide ? "Hide Review from Website?" : "Show Review on Website?",
   message: hide ? "Hide this guest review from the public website? It will no longer be visible on the homepage." : "Publish this guest review publicly in the Guest Reviews section on the homepage?",
   confirmLabel: hide ? "Hide Review" : "Publish Review",
   cancelLabel: "Cancel",
   danger: hide
  });
  if(!ok) return;
  const r=await DB.setReviewHidden(i,hide);
  if(!r.ok){Notice.error("Update Review Failed",r.data.error||"Could not update the review.");return}
  Notice.toast("success",hide?"Review hidden from public view.":"Review restored to public view.");
  show();
 }
 else if(a==="gp"){const g=gk(b);gst(g).p=Number(v);$("#gb").innerHTML=gbody(g)}
 else if(a==="gclr"){const g=gk(b),n=gst(g).n;GS[g]={q:"",f:{},p:1,n};show()}
 else if(a==="cal"){cm.setMonth(cm.getMonth()+Number(v));show()}
 else if(a==="closecv")$("#cvd").close()
 else if(a==="closeasgn"){if(await confirmDiscard())$("#asgn-dlg").close();}
 else if(a==="closemaint"){if(await confirmDiscard())$("#maint-dlg").close();}
 else if(a==="closeclean"){if(await confirmDiscard())$("#clean-dlg").close();}
 else if(a==="closesched")$("#sched-dlg").close()
 else if(a==="closecash")$("#cash-dlg").close()
 else if(a==="closerefund"){if(await confirmDiscard())$("#refund-dlg").close();}
 else if(a==="closerec")$("#rec-dlg").close()
 else if(a==="del-perm"){
  const ab = DB.archivedBookings().find(x=>String(x.id)===String(i)) || {};
  const ok = await Notice.confirm({
   title: "Permanently Delete Archived Record?",
   message: "This action will permanently remove the selected archived record. Related business and transaction history may be affected. This action cannot be undone.",
   details: [
     ["Booking Reference", "#" + (ab.id || i)],
     ["Customer", ab.name || "—"],
     ["Treatment", ab.serviceName || "—"],
     ["Date", when(ab.date) || "—"],
     ["Amount", ab.amount ? peso(ab.amount) : "—"]
   ],
   confirmLabel: "Delete Permanently",
   cancelLabel: "Keep Record",
   danger: true
  });
  if(ok){
   const r = await DB.deleteBookingPermanent(i);
   if(!r.ok){ Notice.error("Deletion Failed", r.data.error || "Could not permanently delete booking."); return; }
   Notice.toast("success", "Archived record permanently deleted.");
   await DB.loadAdmin();
   show();
  }
 }
 else if(a==="deact-pkg"){
  const pkg = DB.packages().find(p=>String(p.id)===String(i));
  if(!pkg) return;
  const act = v === "ACTIVE";
  const ok = await Notice.confirm({
   title: act ? "Reactivate Package?" : "Deactivate Package?",
   message: act ? "Reactivate package \""+esc(pkg.name)+"\" ("+esc(pkg.package_code)+")?" : "Deactivate package \""+esc(pkg.name)+"\" ("+esc(pkg.package_code)+")? It will no longer be available for new bookings.",
   confirmLabel: act ? "Reactivate Package" : "Deactivate Package",
   cancelLabel: "Cancel",
   danger: !act
  });
  if(ok){
   const r = await DB.savePackage({...pkg, package_status: v}, pkg.id);
   if(!r.ok){ Notice.error("Update Package Failed", r.data.error || "Could not update package."); return; }
   Notice.toast("success", act ? "Package \""+pkg.name+"\" reactivated." : "Package \""+pkg.name+"\" deactivated.");
   await DB.loadAdmin();
   show();
  }
 }
 else if(a==="deact-th"){
  const th = DB.therapists().find(t=>String(t.id)===String(i));
  if(!th) return;
  const act = v === "ACTIVE";
  const ok = await Notice.confirm({
   title: act ? "Reactivate Therapist?" : "Deactivate Therapist?",
   message: act ? "Reactivate therapist \""+esc(th.full_name)+"\" ("+esc(th.employee_code)+")?" : "Deactivate therapist \""+esc(th.full_name)+"\" ("+esc(th.employee_code)+")? They will not be assigned to new appointments.",
   confirmLabel: act ? "Reactivate Therapist" : "Deactivate Therapist",
   cancelLabel: "Cancel",
   danger: !act
  });
  if(ok){
   const r = await DB.saveTherapist({...th, employment_status: v}, th.id);
   if(!r.ok){ Notice.error("Update Therapist Failed", r.data.error || "Could not update therapist."); return; }
   Notice.toast("success", act ? "Therapist \""+th.full_name+"\" reactivated." : "Therapist \""+th.full_name+"\" deactivated.");
   await DB.loadAdmin();
   show();
  }
 }
 else if(a==="forfeit-txn"){
  const bkId = b.dataset.b || i;
  const amt = Number(b.dataset.amt || 0);
  const bk = bkAll().find(x=>String(x.id)===String(bkId)) || {};
  const ok = await Notice.confirm({
   title: "Apply Downpayment Forfeiture?",
   message: "Are you sure you want to forfeit this simulated downpayment? The payment status will be marked as FORFEITED in accordance with the 24-hour cancellation policy.",
   details: [
     ["Booking Reference", "#" + bkId],
     ["Customer", bk.name || "—"],
     ["Amount Affected", peso(amt)],
     ["Policy Reason", "Late cancellation (< 24h notice)"],
     ["Existing Payment Status", "PAID (DEMO)"],
     ["Resulting Payment Status", "FORFEITED (DEMO)"]
   ],
   confirmLabel: "Confirm Forfeiture",
   cancelLabel: "Cancel",
   danger: true
  });
  if(!ok) return;
  const r = await DB.forfeitDownpayment(bkId, "Late cancellation forfeiture applied by admin");
  if(!r.ok){ Notice.error("Forfeiture Failed", r.data.error || "Could not forfeit downpayment."); return; }
  Notice.toast("success", "Downpayment forfeited successfully.");
  await DB.loadAdmin();
  show();
 }
 else if(a==="confirm-cash"){
  const tid=i;
  const tx=DB.allTxns().find(x=>String(x.id)===String(tid));
  if(!tx)return;
  const bk = bkAll().find(x=>String(x.id)===String(tx.rawBookingId||tx.bookingId)) || {};
  $("#cash-body").innerHTML=`
    <div class="demo-banner">DEMO PAYMENT SYSTEM — NO REAL MONEY WILL BE TRANSFERRED.</div>
    <table style="width:100%;font-size:.9rem;margin:12px 0;border-collapse:collapse">
     <tr><td style="color:var(--mut);padding:5px 0">Customer:</td><td><b>${esc(tx.customerName||bk.name||"—")}</b></td></tr>
     <tr><td style="color:var(--mut);padding:5px 0">Booking Reference:</td><td><b>${esc(tx.bookingId)}</b></td></tr>
     <tr><td style="color:var(--mut);padding:5px 0">Transaction Type:</td><td><b>${esc((tx.type||"CASH_AT_SPA").replace(/_/g," "))}</b></td></tr>
     <tr><td style="color:var(--mut);padding:5px 0">Amount:</td><td><b style="color:var(--forest);font-size:1.15rem">${peso(tx.amount)}</b></td></tr>
     <tr><td style="color:var(--mut);padding:5px 0">Current Payment Status:</td><td><span class="pill ${tx.status.toLowerCase()}">${esc(tx.status)}</span></td></tr>
     <tr><td style="color:var(--mut);padding:5px 0">Resulting Payment Status:</td><td><span class="pill confirmed">PAID (DEMO)</span></td></tr>
    </table>
    <p class="note">Physical cash collected at front desk. Recording will credit the booking balance and issue a digital receipt.</p>
    <input type="hidden" id="cash_tid" value="${tx.id}">
  `;
  $("#cash-dlg").showModal();
 }
 else if(a==="open-refund-txn"||a==="open-refund-bk"){
  const rawB=b.dataset.b||i;
  const maxAmt=Number(b.dataset.amt||0);
  $("#refund-body").innerHTML=`
    <p>Process simulated demo refund for <strong>${esc(rawB)}</strong>.</p>
    <div class="f">
     <label for="ref_amt">Refund Amount (₱)</label>
     <input type="number" id="ref_amt" step="50" min="1" max="${maxAmt}" value="${maxAmt}" required>
     <small style="color:var(--mut)">Maximum refundable confirmed payment: ${peso(maxAmt)}</small>
    </div>
    <div class="f">
     <label for="ref_reason">Refund Reason</label>
     <input type="text" id="ref_reason" value="Customer request / service adjustment" required>
    </div>
    <input type="hidden" id="ref_bid" value="${esc(rawB)}">
  `;
  trackForm($("#refund-form"));$("#refund-dlg").showModal();
 }
 else if(a==="admin-rec"){
  const res=await DB.getAdminReceipt(i);
  if(res.ok&&res.data&&res.data.receipt){
   const rec=res.data.receipt;
   $("#rec-body").innerHTML=`
    <div class="demo-banner">DEMO PAYMENT RECEIPT — NO REAL MONEY WILL BE TRANSFERRED.</div>
    <div style="background:#fff;border:1px solid #ddd;padding:16px;border-radius:4px">
     <div style="display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:8px">
      <b>${esc(rec.receiptNumber)}</b>
      <span class="pill confirmed">PAID (DEMO)</span>
     </div>
     <table style="width:100%;font-size:.88rem">
      <tr><td>Ref:</td><td><b>${esc(rec.transactionReference)}</b></td></tr>
      <tr><td>Customer:</td><td>${esc(rec.customerName)} (${esc(rec.customerEmail)})</td></tr>
      <tr><td>Booking:</td><td>${esc(rec.bookingReference)}</td></tr>
      <tr><td>Service:</td><td>${esc(rec.serviceName)}</td></tr>
      <tr><td>Appointment:</td><td>${when(rec.appointmentDate)} at ${esc(rec.appointmentTime)}</td></tr>
      <tr><td>Method:</td><td><b>${esc(rec.paymentMethod)}</b></td></tr>
      <tr><td>Type:</td><td>${esc((rec.transactionType||'').replace(/_/g,' '))}</td></tr>
      <tr><td>Amount:</td><td><b style="color:var(--forest)">${peso(rec.amountPaid)}</b></td></tr>
      <tr><td>Remaining Balance:</td><td><b>${peso(rec.remainingBalance)}</b></td></tr>
      ${rec.lockerNumber?`<tr><td>Locker & Passcode:</td><td><b>${esc(rec.lockerNumber)}</b> (Passcode: <code style="font-weight:700">${esc(rec.lockerPasscode)}</code>) — <small>T&amp;C 14</small></td></tr>`:''}
     </table>
    </div>
   `;
   $("#rec-prn-btn").onclick=()=>window.print();
   $("#rec-dlg").showModal();
  }else{
   Notice.warning("Digital Receipt", (res.data&&res.data.error)||"Digital receipt unavailable.");
  }
 }});

const cashBtn=$("#cash-confirm-btn");
if(cashBtn){
 cashBtn.onclick=async()=>{
  if(cashBtn.disabled) return;
  const tid=$("#cash_tid").value;
  const tx=DB.allTxns().find(x=>String(x.id)===String(tid));
  if(!tx) return;
  const bk = bkAll().find(x=>String(x.id)===String(tx.rawBookingId||tx.bookingId)) || {};
  const ok = await Notice.confirm({
   title: "Record Demo Payment?",
   message: "Confirm recording simulated demo payment for this appointment?",
   details: [
     ["Booking Reference", "#" + tx.bookingId],
     ["Customer", tx.customerName || bk.name || "—"],
     ["Payment Method", "Cash at Spa (Demo)"],
     ["Payment Type", (tx.type||"CASH_AT_SPA").replace(/_/g," ")],
     ["Amount", peso(tx.amount)],
     ["Existing Amount Paid", peso(bk.paidAmount || 0)],
     ["Remaining Balance", peso(Math.max(0, (bk.amount||tx.amount) - (bk.paidAmount||0) - tx.amount))],
     ["New Payment Status", "PAID (DEMO)"]
   ],
   confirmLabel: "Confirm Payment",
   cancelLabel: "Cancel"
  });
  if(!ok) return;
  cashBtn.disabled = true;
  cashBtn.textContent = "Recording...";
  try{
   const r=await DB.confirmCashPayment(tid);
   if(!r.ok){ Notice.error("Payment Confirmation Failed", r.data.error||"Could not confirm cash payment."); return; }
   Notice.toast("success", "Payment recorded successfully.");
   await DB.loadAdmin();
   $("#cash-dlg").close();
   show();
  }finally{
   cashBtn.disabled = false;
   cashBtn.textContent = "Confirm Cash Receipt";
  }
 };
}

const refForm=$("#refund-form");
if(refForm){
 refForm.onsubmit=async e=>{
  e.preventDefault();
  const subBtn = refForm.querySelector('button[type="submit"]');
  if(subBtn && subBtn.disabled) return;
  const bid=$("#ref_bid").value;
  const amt=$("#ref_amt").value;
  const reason=$("#ref_reason").value;
  const bk = bkAll().find(x=>String(x.id)===String(bid)) || {};
  const ok = await Notice.confirm({
   title: "Process Demo Refund?",
   message: "Are you sure you want to process a simulated demo refund of "+peso(amt)+" for booking #"+esc(bid)+"?",
   details: [
    ["Customer", bk.name || "—"],
    ["Booking", bid],
    ["Type", "SIMULATED DEMO REFUND"],
    ["Refund Amount", peso(amt)],
    ["Reason", reason || "Customer refund request"]
   ],
   confirmLabel: "Issue Demo Refund",
   cancelLabel: "Cancel",
   danger: true
  });
  if(!ok) return;
  if(subBtn){ subBtn.disabled = true; subBtn.textContent = "Processing..."; }
  try{
   const r=await DB.processRefund(bid,amt,reason);
   if(!r.ok){ Notice.error("Refund Failed", r.data.error||"Could not process refund."); return; }
   Notice.toast("success", "Simulated demo refund of " + peso(amt) + " processed and credited immediately (~3s demo).");
   clearDirty();
   await DB.loadAdmin();
   $("#refund-dlg").close();
   show();
  }finally{
   if(subBtn){ subBtn.disabled = false; subBtn.textContent = "Issue Demo Refund"; }
  }
 };
}

document.addEventListener("submit",async e=>{
 if(e.target.id==="pay-sett-form"){
  e.preventDefault();
  const ok = await Notice.confirm({
   title: "Save System Changes?",
   message: "Save updated payment and downpayment rules? These rules will take effect immediately for all upcoming appointments and transactions.",
   confirmLabel: "Save Changes",
   cancelLabel: "Cancel"
  });
  if(!ok) return;
  await DB.savePaymentSetting("DOWNPAYMENT_PERCENTAGE",$("#set-dp").value);
  await DB.savePaymentSetting("MIN_DOWNPAYMENT_AMOUNT",$("#set-mindp").value);
  await DB.savePaymentSetting("ALLOW_FULL_PAYMENT",$("#set-allowfull").checked?"1":"0");
  await DB.savePaymentSetting("FORFEIT_LATE_CANCELLATION",$("#set-forfeit").checked?"1":"0");
  await DB.savePaymentSetting("DEMO_MODE_ACTIVE",$("#set-demo").checked?"1":"0");
  clearDirty();
  await DB.loadAdmin();
  Notice.toast("success", "Payment settings saved successfully.");
  show();
  return;
 }
 if(e.target.id==="apw"){
  e.preventDefault();
  const m=$("#apwm");
  if($("#pn").value!==$("#pf").value){m.style.color="#a33";m.textContent="Passwords do not match.";return}
  const ok = await Notice.confirm({
   title: "Change Admin Password?",
   message: "Are you sure you want to change your admin password? You will need to use this new password on your next login.",
   confirmLabel: "Change Password",
   cancelLabel: "Cancel",
   danger: true
  });
  if(!ok) return;
  const r=await DB.changePassword($("#pc").value,$("#pn").value,$("#pf").value);
  m.style.color=r.ok?"#2f6b3a":"#a33";
  m.textContent=r.ok?"Password updated.":(r.data.error||"Could not update the password.");
  if(r.ok){
   Notice.toast("success","Admin password updated successfully.");
   e.target.reset();
  }
  return;
 }
});
let gT;document.addEventListener("input",e=>{const t=e.target;if(!t.classList||!t.classList.contains("gq"))return;clearTimeout(gT);gT=setTimeout(()=>{const g=gk(t),st=gst(g);st.q=t.value;st.p=1;$("#gb").innerHTML=gbody(g)},150)});
document.addEventListener("change",e=>{const t=e.target;if(!t.closest||!t.closest("[data-g]"))return;const g=gk(t),st=gst(g);
 if(t.classList.contains("gf")){st.f[t.dataset.f]=t.value;st.p=1}else if(t.classList.contains("gn")){st.n=Number(t.value);st.p=1}else return;$("#gb").innerHTML=gbody(g)});
$("#bg").onclick=()=>{
  const o=$("#sb").classList.toggle("open");
  $("#bg").setAttribute("aria-expanded",o);
  const bd=document.getElementById("sb-backdrop");
  if(bd) bd.classList.toggle("open",o);
};
const bd=document.getElementById("sb-backdrop");
if(bd){
  bd.onclick=()=>{
    $("#sb").classList.remove("open");
    $("#bg").setAttribute("aria-expanded","false");
    bd.classList.remove("open");
  };
}
const outBtn = $("#out");
if(outBtn){
 outBtn.onclick = async e => {
  e.preventDefault();
  if(typeof hasUnsavedChanges === "function" && hasUnsavedChanges()){
   const discard = await confirmDiscard();
   if(!discard) return;
  }
  const notifier = (typeof Notice !== "undefined" && Notice) || (typeof window !== "undefined" && window.Notice);
  if(notifier && typeof notifier.confirm === "function"){
   const ok = await notifier.confirm({
    title: "Confirm Logout",
    message: "Are you sure you want to log out of your admin account?",
    confirmLabel: "Yes, Log Out",
    cancelLabel: "Stay Logged In",
    danger: true
   });
   if(!ok) return;
  }
  if(typeof clearDirty === "function") clearDirty();
  await Auth.logout(true);
 };
}
})();
