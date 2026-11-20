/* CUSTOMER PORTAL. Data comes from the server via DB.* (js/db.js); the server shows only the logged-in customer's own records. */
(async()=>{
const msg=t=>{document.body.innerHTML='<p style="font:16px/1.6 sans-serif;max-width:520px;margin:15vh auto;padding:0 20px">'+t+'</p>'};
const s=await Auth.me();
if(s.offline)return msg("<b>The account service is not available right now.</b><br>Please try again shortly.");
if(!s.authenticated){location.replace("login.html?next=account.html");return}
if(s.user&&s.user.isAdmin){location.replace("admin.html");return}
Auth.weakNotice(s);   /* admins have their own dashboard, not the customer portal */
const outBtn = document.getElementById("out");
if(outBtn){
  outBtn.onclick = async e => {
    if(e) e.preventDefault();
    const notifier = (typeof Notice !== "undefined" && Notice) || (typeof window !== "undefined" && window.Notice);
    if(notifier && typeof notifier.confirm === "function"){
      const ok = await notifier.confirm({
        title: "Confirm Logout",
        message: "Are you sure you want to log out of your account?",
        confirmLabel: "Yes, Log Out",
        cancelLabel: "Stay Logged In",
        danger: true
      });
      if(!ok) return;
    }
    await Auth.logout(true);
  };
}
const ld=await DB.loadPortal();
if(!ld.ok)return msg("<b>We could not load your account.</b><br>Please refresh the page or try again shortly.");
const checkPassword=p=>({length:p.length>=8,upper:/[A-Z]/.test(p),lower:/[a-z]/.test(p),number:/[0-9]/.test(p),special:/[^A-Za-z0-9\s]/.test(p)});
const $=s=>document.querySelector(s),esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const TABS=[["overview","Overview"],["profile","Profile"],["bookings","My Bookings"],["transactions","Transaction History"],["favorites","Favorites"],["notifications","Notifications"],["settings","Account Settings"]];
let storedSrt = null;
try { storedSrt = sessionStorage.getItem("bvn_srt"); } catch(e){}
let U, C, flt = "ALL", srt = (storedSrt && ["recently-booked","soonest","latest","oldest-booked"].includes(storedSrt)) ? storedSrt : "recently-booked", tf = "ALL", tq = "";

function today(date = new Date()) {
  try {
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    }).format(date);
  } catch (e) {
    const manilaMs = date.getTime() + (8 * 60 * 60 * 1000);
    return new Date(manilaMs).toISOString().slice(0, 10);
  }
}
const pad = n => String(n).padStart(2, "0");
const sortKeyCreated = item => {
  if (item.createdAt) return String(item.createdAt);
  const num = Number(item.rawId || item.numericId || String(item.id || "").replace(/\D/g, "")) || 0;
  return "ID-" + String(num).padStart(10, "0");
};
const sortKeyAppt = item => {
  return (item.date || "9999-99-99") + " " + (item.time || "00:00");
};
const fd = d => {
  if (!d) return "—";
  try {
    const p = d.split("-").map(Number);
    if (p.length === 3) {
      const dt = new Date(Date.UTC(p[0], p[1] - 1, p[2], 12, 0, 0));
      return dt.toLocaleDateString("en-US", { timeZone: "Asia/Manila", year: "numeric", month: "long", day: "numeric" });
    }
  } catch (e) {}
  return d;
};
const fdt = d => {
  if (!d) return "—";
  try {
    return new Date(d).toLocaleDateString("en-US", { timeZone: "Asia/Manila", month: "short", day: "numeric" });
  } catch (e) {
    return d;
  }
};
const ft = t => {
  if (!t) return "—";
  const [h, m] = t.split(":");
  const nh = Number(h);
  return ((nh % 12) || 12) + ":" + m + " " + (nh < 12 ? "AM" : "PM");
};
const price = b => b.amount > 0 ? "₱" + Number(b.amount).toLocaleString() : "To be confirmed";
const peso = n => "₱" + Number(n).toLocaleString();

/* Eligible upcoming / active appointments: PENDING, CONFIRMED, IN_PROGRESS with appointment date >= Manila today */
const up = b => {
  if (!b || !b.date) return false;
  const eligible = ["PENDING", "CONFIRMED", "IN_PROGRESS"];
  if (!eligible.includes(b.status)) return false;
  return b.date >= today();
};

const cat = () => DB.catalog().services || [];
const stars = n => "★".repeat(n) + "☆".repeat(5 - n);
const canPrint = b => b.status === "CONFIRMED" || b.status === "COMPLETED";
const flash = (ok, okMsg, errMsg) => {
  const n = document.createElement("p");
  n.className = ok ? "ok" : "er";
  n.setAttribute("role", "status");
  n.textContent = ok ? okMsg : errMsg;
  $("#m").prepend(n);
  setTimeout(() => n.remove(), ok ? 3500 : 7000);
};
const f12 = t => {
  const [h, m] = t.split(":").map(Number);
  return (h % 12 || 12) + ":" + String(m).padStart(2, "0") + " " + (h < 12 ? "AM" : "PM");
};
const empty = (t, p, l, h) => `<div class="empty"><svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M32 58V14M32 44C14 40 10 24 18 12c10 5 14 17 14 32zM32 34c14-4 18-16 12-26-9 4-12 14-12 26z"/></svg><h3>${t}</h3><p>${p}</p>${l ? `<a class="btn" href="${h || "services.html"}">${l}</a>` : ""}</div>`;

/* Authoritative financial & refund calculation for booking cards */
function getBookingFinancials(b) {
  const origAmount = Number(b.amount || 0);
  const pst = b.paymentStatus || "UNPAID";
  const isTerminal = ["CANCELLED", "REJECTED"].includes(b.status);

  // Preferred authoritative figures from server shape_booking / portal_bookings
  let totalPaid = (b.totalPaid !== undefined) ? Number(b.totalPaid) : Number(b.paidAmount || 0);
  let totalRefunded = (b.totalRefunded !== undefined) ? Number(b.totalRefunded) : 0;

  // Cross-reference with customer transaction ledger
  const txList = DB.txns(C.id) || [];
  const bTxns = txList.filter(t => {
    return String(t.bookingId) === String(b.id) ||
           (t.rawBookingId && String(t.rawBookingId) === String(b.rawId || b.id)) ||
           (t.bookingId && b.id && t.bookingId.replace(/[^0-9]/g, '') === b.id.replace(/[^0-9]/g, ''));
  });

  if (bTxns.length > 0) {
    let txPaid = 0, txRef = 0;
    bTxns.forEach(t => {
      const amt = Number(t.amount || 0);
      const type = (t.transactionType || t.type || "").toUpperCase();
      if (t.status === "PAID") {
        if (type === "REFUND") txRef += amt;
        else txPaid += amt;
      }
    });
    if (txPaid > 0) totalPaid = txPaid;
    if (txRef > 0) totalRefunded = txRef;
  }

  const netPaid = Math.max(0, totalPaid - totalRefunded);

  // Outstanding payable amount:
  // Cancelled, rejected, refunded, or forfeited bookings have NO outstanding amount due
  let outstanding = 0;
  let isOutstandingApplicable = true;
  if (isTerminal || pst === "REFUNDED" || pst === "FORFEITED") {
    outstanding = 0;
    isOutstandingApplicable = false;
  } else {
    outstanding = Math.max(0, origAmount - netPaid);
    if (b.status === "COMPLETED" && netPaid >= origAmount) {
      outstanding = 0;
    }
  }

  const canPay = !isTerminal && isOutstandingApplicable && outstanding > 0 && ["PENDING", "CONFIRMED"].includes(b.status);

  return {
    origAmount,
    totalPaid,
    totalRefunded,
    netPaid,
    outstanding,
    isOutstandingApplicable,
    canPay,
    paymentStatus: pst,
    isTerminal
  };
}

function bcard(b, i, isFeatured = false) {
  const a = up(b);
  const fin = getBookingFinancials(b);
  const pst = fin.paymentStatus;
  const pbadge = `<span class="badge ${pst}" style="margin-left:6px">${pst.replace(/_/g, " ")}</span>`;
  const canPay = fin.canPay;
  const payBtnText = (pst === "UNPAID") ? "Pay Downpayment" : "Pay Balance";

  let balanceDisplay = "";
  if (!fin.isOutstandingApplicable) {
    if (pst === "REFUNDED") {
      balanceDisplay = `<span style="color:#2e7d32;font-weight:600">None (Fully Refunded)</span>`;
    } else if (pst === "FORFEITED") {
      balanceDisplay = `<span style="color:#b91c1c;font-weight:600">None (Forfeited)</span>`;
    } else {
      balanceDisplay = `<span style="color:#666;font-weight:600">None (Cancelled)</span>`;
    }
  } else if (fin.origAmount === 0 && pst === "UNPAID") {
    balanceDisplay = `<span style="color:var(--mut)">To be confirmed</span>`;
  } else if (fin.outstanding === 0) {
    balanceDisplay = `<span style="color:#2e7d32;font-weight:600">₱0.00 (Fully Paid)</span>`;
  } else {
    balanceDisplay = `<b>${peso(fin.outstanding)}</b>`;
  }

  let metaItems = `
    <span>Date <b>${fd(b.date)}</b></span>
    <span>Time <b>${ft(b.time)}</b></span>
    <span>Duration <b>${b.duration ? esc(b.duration) + " min" : "—"}</b></span>
    <span>Guests <b>${b.guests || 1}</b></span>
    <span>Suite <b>${esc(b.roomName || "Spa Suite")}</b></span>
    <span>Total <b>${price(b)}</b></span>
    <span>Paid <b>${peso(fin.totalPaid)}</b></span>
  `;

  if (fin.totalRefunded > 0) {
    metaItems += `<span>Refunded <b style="color:#2e7d32">${peso(fin.totalRefunded)} (Demo)</b></span>`;
    metaItems += `<span>Net Paid <b>${peso(fin.netPaid)}</b></span>`;
  }

  metaItems += `<span>Balance Due ${balanceDisplay}</span>`;
  metaItems += `<span>ID <b>${esc(b.id)}</b></span>`;

  return `<article class="bk" style="--i:${i || 0}">
    <div class="top">
      <div>
        <span class="eb" style="color:var(--gd);margin:0 0 4px">${a ? (isFeatured ? "Next Upcoming Reservation" : "Upcoming reservation") : esc(b.id)}</span>
        <h3>${esc(b.serviceName)}</h3>
      </div>
      <div>
        <span class="badge ${b.status}">${b.status}</span>
        ${pbadge}
      </div>
    </div>
    <div class="meta">${metaItems}</div>
    <div class="act">
      <button class="btn line sm" data-a="view" data-i="${esc(b.id)}">View details</button>
      ${canPay ? `<button class="btn sm" data-a="pay" data-i="${esc(b.id)}" style="background:var(--forest);color:var(--iv)">${payBtnText}</button>` : ""}
      ${a && !(b.reschedule && b.reschedule.status === "PENDING") ? `<button class="btn line sm" data-a="rsq" data-i="${esc(b.id)}">Request reschedule</button>` : ""}
      ${canPrint(b) ? `<button class="btn line sm" data-a="pr" data-i="${esc(b.id)}">Print confirmation</button>` : ""}
      ${b.status === "COMPLETED" && !b.review ? `<button class="btn sm" data-a="rvq" data-i="${esc(b.id)}">Leave a review</button>` : ""}
      ${a ? `<button class="btn line sm dg" data-a="cancel" data-i="${esc(b.id)}">Cancel booking</button>` : ""}
      ${b.status === "COMPLETED" ? `<a class="btn sm" href="contact.html?service=${encodeURIComponent(b.serviceName)}#booking">Book again</a>` : ""}
      ${["COMPLETED", "CANCELLED", "NO-SHOW", "REJECTED"].includes(b.status) ? `<button class="btn line sm" data-a="hide" data-i="${esc(b.id)}">Remove from my list</button>` : ""}
    </div>
    ${xtra(b, a)}
  </article>`;
}
function xtra(b,a){const r=b.reschedule,pend=r&&r.status==="PENDING";let h="";
 if(pend)h+=`<p class="note" style="margin:0">Reschedule requested: <b>${fd(r.date)}, ${ft(r.time)}</b>. Waiting for the spa. Your current time stays until it is approved. <button type="button" class="lk" data-a="rwd" data-i="${r.id}">Withdraw request</button></p>`;
 else if(r&&r.status==="DECLINED"&&a)h+=`<p class="note" style="margin:0">Your last reschedule request was not approved${r.adminNote?": "+esc(r.adminNote):""}. Your booking stays on ${fd(b.date)}.</p>`;
 if(b.review)h+=`<p class="note" style="margin:0"><span class="stars" aria-label="Your rating: ${b.review.rating} of 5">${stars(b.review.rating)}</span> Your review${b.review.comment?": \u201C"+esc(b.review.comment)+"\u201D":""}</p>`;
 return h}
const tline=l=>l.length?`<ul class="tl">${l.map((x,i)=>`<li style="--i:${i}"><b>${esc(x.text)}</b><small>${fdt(x.at)}</small></li>`).join("")}</ul>`:'<p class="note">No activity yet.</p>';
function recs(bks){const sv=cat();if(!sv.length)return"";const last=bks.find(b=>b.status==="COMPLETED")||bks[0],booked=bks.map(b=>b.serviceName);
 const ref=last&&sv.find(s=>s.name===last.serviceName);let r=ref?sv.filter(s=>s.cat===ref.cat&&!booked.includes(s.name)):[];
 r=r.concat(sv.filter(s=>!booked.includes(s.name)&&!r.includes(s))).slice(0,3);if(!r.length)return"";
 return `<h2>${last?"Recommended For You":"Discover Our Services"}</h2><p class="note" style="margin:-8px 0 16px">${ref?`Because you booked ${esc(ref.name)}. Suggestions come from simple category rules based on your bookings.`:"Suggestions come from simple category rules."}</p><div class="rec">${r.map((s,i)=>`<a class="rc st" style="--i:${i};text-decoration:none" href="services.html"><span class="eb" style="margin:0;color:var(--gd)">${esc(s.cat||"Wellness")}</span><h3>${esc(s.name)}</h3><p>${esc(s.desc||"")}</p></a>`).join("")}</div>`}
const V={
 overview(){
  const b = DB.bookings(C.id);
  const upList = b.filter(up);
  // Sort soonest appointment first
  const n = upList.slice().sort((x, y) => sortKeyAppt(x).localeCompare(sortKeyAppt(y)))[0];
  const cnt = [
    ["Upcoming Booking", upList.length],
    ["Total Bookings", b.length],
    ["Completed", b.filter(x => x.status === "COMPLETED").length],
    ["Cancelled", b.filter(x => x.status === "CANCELLED").length]
  ];
  return `<p class="note" style="margin:20px 0;font-size:.88rem;color:var(--forest)">Signed in as <b>${esc(U.fullName)}</b> (${esc(U.email)}) &bull; Customer Reference: <b style="color:var(--gd)">${esc(C.id)}</b></p>
  <div class="cards">${cnt.map(([l, v], i) => `<div class="sc st" style="--i:${i}"><b>${pad(v)}</b><small>${l}</small></div>`).join("")}</div>
  <div class="g2">
    <div>
      <h2>Upcoming Reservation</h2>
      ${n ? bcard(n, 0, true) : empty("No Upcoming Reservations", "Your next moment of relaxation is waiting.", "Explore services")}
    </div>
    <div>
      <h2>Recent Activity</h2>
      <div class="pnl">${tline(DB.activity(C.id).slice(0, 6))}</div>
    </div>
  </div>
  ${recs(b)}`;
 },
 profile(){
  const ini = U.fullName.split(" ").map(x => x[0]).slice(0, 2).join("").toUpperCase();
  const mo = new Date(C.createdAt).toLocaleDateString("en-US", { month: "long", year: "numeric" });
  const v = x => x ? esc(x) : '<span style="color:var(--mut)">Not added yet</span>';
  return `<div class="pf"><div class="av" aria-hidden="true">${esc(ini)}</div><div><span class="eb" style="color:var(--gd)">My Profile</span><h2 style="margin:0">${esc(U.fullName)}</h2><p style="margin:4px 0">${esc(U.email)} &bull; ${esc(C.id)}</p><small>Member since ${mo}</small></div></div>
  <div class="pnl"><div class="top"><h2 style="margin:0">Personal Information</h2><button class="btn line sm" data-a="edit">Edit profile</button></div><dl style="margin-top:14px"><dt>Full name</dt><dd>${esc(U.fullName)}</dd><dt>Email</dt><dd>${esc(U.email)}</dd><dt>Customer ID</dt><dd><b>${esc(C.id)}</b></dd><dt>Phone number</dt><dd>${v(C.phone)}</dd><dt>Birthday</dt><dd>${C.birthday?fd(C.birthday):v("")}</dd><dt>Preferred contact</dt><dd>${v(C.contact)}</dd></dl><p id="pm" role="status"></p></div>`;
 },
 bookings(){
  let b = DB.bookings(C.id);
  if (flt === "UPCOMING") b = b.filter(up);
  else if (flt !== "ALL") b = b.filter(x => x.status === flt);

  // Sorting (Default locked to recently-booked: most recent reservation first)
  if (srt === "soonest") {
    b = b.slice().sort((x, y) => sortKeyAppt(x).localeCompare(sortKeyAppt(y)));
  } else if (srt === "latest") {
    b = b.slice().sort((x, y) => sortKeyAppt(y).localeCompare(sortKeyAppt(x)));
  } else if (srt === "oldest-booked") {
    b = b.slice().sort((x, y) => sortKeyCreated(x).localeCompare(sortKeyCreated(y)));
  } else {
    // Default fallback to recently-booked
    b = b.slice().sort((x, y) => sortKeyCreated(y).localeCompare(sortKeyCreated(x)));
  }

  return `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
    <h2 style="margin:0">My Reservations</h2>
    <button type="button" class="btn line sm" id="btn-refresh-portal" data-a="refresh-portal" style="display:inline-flex;align-items:center;gap:6px">
      <span style="font-size:1.1rem;line-height:1">&#x21BB;</span> Refresh Bookings
    </button>
  </div>
  <p class="note" style="margin:0 0 16px;font-size:.88rem;color:var(--forest)">Viewing reservations for <b>${esc(U.fullName)}</b> (${esc(U.email)}) &bull; Customer Reference: <b style="color:var(--gd)">${esc(C.id)}</b></p>
  <div class="chips" role="group" aria-label="Filter bookings">
    ${[["ALL","All"],["UPCOMING","Upcoming"],["COMPLETED","Completed"],["CANCELLED","Cancelled"]].map(([k,l]) =>
      `<button class="chip" data-a="f" data-v="${k}" aria-pressed="${flt===k}">${l}</button>`
    ).join("")}
    <select id="srt" aria-label="Sort bookings" style="margin-left:auto">
      <option value="recently-booked"${srt==="recently-booked"?" selected":""}>Recently Booked</option>
      <option value="soonest"${srt==="soonest"?" selected":""}>Soonest Appointment</option>
      <option value="latest"${srt==="latest"?" selected":""}>Latest Appointment</option>
      <option value="oldest-booked"${srt==="oldest-booked"?" selected":""}>Oldest Booked</option>
    </select>
  </div>` +
  (DB.bookings(C.id).length ? (b.length ? b.map(bcard).join("") : empty("No matching bookings", "Try selecting 'All' or a different filter.")) : empty("Your Wellness Journey Begins Here", "You haven't made a reservation yet.", "Explore our services"));
 },
 transactions(){let t=DB.txns(C.id);const all=t.length;if(tf!=="ALL")t=t.filter(x=>x.status===tf);const q=tq.toLowerCase();if(q)t=t.filter(x=>[x.id,x.transactionRef,x.bookingId,x.description,x.paymentMethodLabel].join(" ").toLowerCase().includes(q));
  return `<div class="demo-banner">DEMO PAYMENT — NO REAL MONEY WILL BE TRANSFERRED. All payments, cards, and receipts are simulations.</div>
  <div class="chips" role="group" aria-label="Filter transactions">${["ALL","PAID","PENDING","REFUNDED","FORFEITED","FAILED"].map(k=>`<button class="chip" data-a="tf" data-v="${k}" aria-pressed="${tf===k}">${k[0]+k.slice(1).toLowerCase().replace(/_/g," ")}</button>`).join("")}<input type="search" id="tq" placeholder="Search transaction..." aria-label="Search transactions" value="${esc(tq)}"></div>`+
  (t.length?`<table class="rs"><thead><tr><th>Date</th><th>Reference</th><th>Method</th><th>Type</th><th>Booking</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead><tbody>${t.map(x=>`<tr><td data-l="Date">${fd(x.createdAt.slice(0,10))}</td><td data-l="Reference"><b>${esc(x.transactionRef||x.id)}</b></td><td data-l="Method">${esc(x.paymentMethodLabel||x.paymentMethod||"Demo")}</td><td data-l="Type"><small>${esc((x.transactionType||"DOWNPAYMENT").replace(/_/g," "))}</small></td><td data-l="Booking">${esc(x.bookingId)}</td><td data-l="Amount">${peso(x.amount)}</td><td data-l="Status"><span class="badge ${x.status}">${x.status}</span></td><td data-l="Receipt">${x.status==="PAID"?`<button class="btn line sm" data-a="rec" data-i="${esc(x.id)}">Receipt</button>`:"\u2014"}</td></tr>`).join("")}</tbody></table>`:empty(all?"No matching transactions.":"No transactions yet.",all?"Try a different filter or search.":"Transactions appear once a booking is created or paid.")) +
  '<p class="note" style="margin-top:16px">Note: All transactions are demo records for testing and demonstration purposes only.</p>'},
 favorites(){const d=DB.catalog(),items=(d.services||[]).map(s=>({k:"s:"+s.name,n:s.name,t:s.cat||"Service",h:"services.html"})).concat((d.rooms||[]).map(r=>({k:"r:"+r.name,n:r.name,t:"Accommodation",h:"accommodations.html"}))),f=DB.favs(C.id);
  if(!items.length)return empty("Nothing saved yet.","Discover something you'll love.","Explore the spa","services.html");
  const sv=items.filter(i=>f.includes(i.k));
  return `<h2>Your Favorites</h2>${sv.length?"":empty("Nothing saved yet.","Discover something you'll love below.")}<div class="rec" style="margin-bottom:36px">${sv.map(fv).join("")}</div><h2>Discover</h2><div class="rec">${items.filter(i=>!f.includes(i.k)).map(fv).join("")}</div>`;function fv(i){return `<div class="rc"><span class="eb" style="margin:0;color:var(--gd)">${esc(i.t)}</span><h3><a href="${i.h}" style="text-decoration:none">${esc(i.n)}</a></h3><button class="heart" data-a="fav" data-v="${esc(i.k)}" aria-pressed="${f.includes(i.k)}" aria-label="${f.includes(i.k)?"Remove":"Save"} ${esc(i.n)} ${f.includes(i.k)?"from":"to"} favorites">${f.includes(i.k)?"\u2665":"\u2661"}</button></div>`}},
 notifications(){
  const td = today();
  const rm = DB.bookings(C.id).filter(b => b.status === "CONFIRMED").map(b => {
    const bDate = new Date(b.date + "T00:00:00+08:00");
    const tDate = new Date(td + "T00:00:00+08:00");
    const diffDays = Math.round((bDate - tDate) / 864e5);
    return diffDays === 1 ? { text: `Your appointment is tomorrow at ${ft(b.time)}.`, at: new Date().toISOString(), read: false } :
           diffDays === 0 ? { text: `Your appointment is today at ${ft(b.time)}.`, at: new Date().toISOString(), read: false } : null;
  }).filter(Boolean).filter(() => C.preferences.bookingReminders);
  const l=rm.concat(DB.notifs(C.id));return `<div class="top" style="margin-bottom:12px"><h2 style="margin:0">Notifications</h2><button class="btn line sm" data-a="read">Mark all as read</button></div>`+(l.length?l.map(n=>`<div class="nt ${n.read?"":"u"}"><i></i><div>${esc(n.text)}<small>${fdt(n.at)}</small></div></div>`).join(""):empty("You're all caught up.","New updates about your reservations appear here."))},
 settings(){const p=C.preferences,t=(k,l)=>`<label class="tg">${l}<input type="checkbox" data-a="pref" data-v="${k}"${p[k]?" checked":""}></label>`;
  return `<div class="g2"><div class="pnl"><h2>Account</h2><div class="f"><label for="em">Email</label><input id="em" type="email" value="${esc(U.email)}" readonly aria-describedby="emn"></div><p class="note" id="emn" style="margin:-8px 0 20px">To change your email address, please contact the spa.</p>
  <h3 style="margin-bottom:12px">Change password</h3><form id="pw" novalidate><div class="f"><label for="cp">Current password</label><input id="cp" type="password" autocomplete="current-password"></div><div class="f"><label for="np">New password</label><input id="np" type="password" autocomplete="new-password"></div>
  <div class="str" id="str"><div>Password strength: <b id="lv">\u2014</b></div><div class="segs"><i></i><i></i><i></i><i></i><i></i></div><ul class="rq"><li data-k="length">At least 8 characters</li><li data-k="upper">Uppercase letter</li><li data-k="lower">Lowercase letter</li><li data-k="number">Number</li><li data-k="special">Special character</li></ul></div>
  <div class="f"><label for="cn">Confirm new password</label><input id="cn" type="password" autocomplete="new-password"></div><button class="btn" type="submit">Update password</button><p id="pwm" role="status"></p></form></div>
  <div><div class="pnl"><h2>Preferences</h2>${t("emailNotifications","Email notifications")}${t("bookingReminders","Booking reminders")}${t("promotions","Promotional notifications")}</div></div></div>`}};
function render(){const k=V[(location.hash||"#overview").slice(1)]?(location.hash||"#overview").slice(1):"overview",un=DB.notifs(C.id).filter(n=>!n.read).length;
 $("#tabs .w").innerHTML=TABS.map(([h,l])=>`<a href="#${h}"${h===k?' aria-current="page"':""}>${l}${h==="notifications"&&un?`<span class="dot">${un}</span>`:""}</a>`).join("");
 $("#m").innerHTML=V[k]();$("#m").style.animation="none";$("#m").offsetWidth;$("#m").style.animation="";if(k==="settings")bindPw();}
function init(){
  C = DB.customer();
  if(!C) return;
  U = { fullName: C.fullName, email: C.email };
  const shortName = U.fullName.split(" ")[0] || U.fullName;
  $("#wl").textContent = "Welcome back, " + shortName;
  
  const heroName = $("#heroAccName"), heroEmail = $("#heroAccEmail"), heroCid = $("#heroAccCid");
  if(heroName) heroName.textContent = U.fullName;
  if(heroEmail) heroEmail.textContent = U.email;
  if(heroCid) heroCid.textContent = C.id;
  
  const accName = $("#accName"), accEmail = $("#accEmail"), accCid = $("#accCid");
  if(accName) accName.textContent = U.fullName;
  if(accEmail) accEmail.textContent = U.email;
  if(accCid) accCid.textContent = C.id;
}
function bindPw(){const np=$("#np"),f=$("#pw");const col=["","#a34a3c","#a34a3c","#b8863b","#71806D","#203A2A"];
 np.addEventListener("input",()=>{const c=checkPassword(np.value),n=Object.values(c).filter(Boolean).length;$("#str").style.setProperty("--c",col[n]);$("#lv").textContent=np.value?["","Weak","Weak","Fair","Good","Strong"][n]:"\u2014";document.querySelectorAll(".segs i").forEach((s,i)=>s.classList.toggle("on",!!np.value&&i<n));document.querySelectorAll(".rq li").forEach(l=>l.classList.toggle("y",c[l.dataset.k]))});
 f.addEventListener("submit",async e=>{
  e.preventDefault();
  const m=$("#pwm");
  if($("#np").value!==$("#cn").value){m.className="er";m.textContent="Passwords do not match.";return}
  if(window.Notice&&Notice.confirm){
   const ok=await Notice.confirm({
    title:"Update Password?",
    message:"Are you sure you want to change your account password?",
    confirmLabel:"Update Password",
    cancelLabel:"Cancel"
   });
   if(!ok)return;
  }
  const r=await DB.changePassword($("#cp").value,np.value,$("#cn").value);
  m.className=r.ok?"ok":"er";
  m.textContent=r.ok?"\u2713 Password updated successfully.":(r.data.error||"Could not update the password.");
  if(r.ok){
   if(window.Notice&&Notice.toast)Notice.toast("success","Password updated successfully.");
   f.reset();
   np.dispatchEvent(new Event("input"));
   DB.loadPortal().then(()=>{C=DB.customer()});
  }
 });}
/* printable booking confirmation: built into a hidden block that only shows when printing (see @media print in account.html) */
async function printBk(bk){const info={};try{const r=await Auth.api("/api/content");if(r.ok)(r.data.content||[]).forEach(x=>{if(x&&x.section)info[x.section]=x.text})}catch{}
 let el=document.getElementById("pr");if(!el){el=document.createElement("div");el.id="pr";document.body.append(el)}
 const row=(l,v)=>`<tr><th scope="row">${l}</th><td>${v}</td></tr>`,done=bk.status==="COMPLETED";
 el.innerHTML=`<div class="prh"><img id="prl" src="assets/logo/logo.png" alt=""><div><h1>Bella Vista Oasis Spa</h1>${["Address","Phone","Email"].filter(k=>info[k]).map(k=>`<p>${esc(info[k])}</p>`).join("")}</div></div>
 <h2>${done?"Booking Summary":"Booking Confirmation"}</h2><p class="prs">Reference <b>${esc(bk.id)}</b></p>
 <table class="prt">${row("Guest",esc(U.fullName)+"<br>"+esc(U.email))}${row("Service",esc(bk.serviceName))}${row("Date",fd(bk.date))}${row("Time",ft(bk.time))}${row("Duration",bk.duration?esc(bk.duration)+" minutes":"\u2014")}${row("Price",price(bk))}${row("Status",esc(bk.status.charAt(0)+bk.status.slice(1).toLowerCase()))}${row("Booked on",fd(bk.createdAt.slice(0,10)))}</table>
 <ul class="prn">${done?"":"<li>Please arrive about 10 minutes before your appointment.</li><li>To change or cancel, use My Account on our website or contact the spa.</li>"}${info["Opening hours"]?`<li>Opening hours: ${esc(info["Opening hours"])}</li>`:""}<li>The price shown is the amount recorded by the spa. No payment is taken online.</li></ul>
 <p class="prf">Printed on ${new Date().toLocaleDateString("en-US",{year:"numeric",month:"long",day:"numeric"})}</p>`;
 const im=$("#prl");try{await im.decode()}catch{}
 window.print()}

function printReceipt(rec){
 let el=document.getElementById("pr");if(!el){el=document.createElement("div");el.id="pr";document.body.append(el)}
 const row=(l,v)=>`<tr><th scope="row">${l}</th><td>${v}</td></tr>`;
 el.innerHTML=`<div class="prh"><img id="prl" src="assets/logo/logo.png" alt=""><div><h1>Bella Vista Oasis Spa</h1><p style="color:#b45309;font-weight:600">DEMO PAYMENT RECEIPT — NO REAL MONEY WILL BE TRANSFERRED.</p></div></div>
 <h2>Digital Demo Payment Receipt</h2><p class="prs">Receipt No: <b>${esc(rec.receiptNumber)}</b> &bull; Ref: <b>${esc(rec.transactionReference)}</b></p>
 <table class="prt">
  ${row("Customer",esc(rec.customerName)+" &lt;"+esc(rec.customerEmail)+"&gt;")}
  ${row("Booking Ref",esc(rec.bookingReference))}
  ${row("Service",esc(rec.serviceName))}
  ${row("Appointment Date & Time",fd(rec.appointmentDate)+" at "+ft(rec.appointmentTime))}
  ${row("Guests",rec.guestCount||1)}
  ${row("Payment Method",esc(rec.paymentMethod))}
  ${row("Transaction Type",esc((rec.transactionType||"").replace(/_/g," ")))}
  ${row("Amount Paid",peso(rec.amountPaid))}
  ${row("Total Booking Amount",peso(rec.totalBookingAmount))}
  ${row("Remaining Balance",peso(rec.remainingBalance))}
  ${row("Payment Status",esc(rec.paymentStatus))}
  ${rec.lockerNumber?row("Locker & Passcode",`<b>${esc(rec.lockerNumber)}</b> &bull; Passcode: <b style="font-family:monospace;letter-spacing:2px;font-size:1.1rem">${esc(rec.lockerPasscode)}</b> (T&amp;C 14)`):""}
  ${row("Processed At",fd(rec.transactionDate.slice(0,10))+" "+ft(rec.transactionDate.slice(11,16)))}
 </table>
 <ul class="prn">
  <li><strong>SIMULATION ONLY:</strong> This receipt certifies a simulated demo transaction. No real money was transferred or charged.</li>
  <li>Bella Vista Oasis Spa operates in Asia/Manila timezone (1:00 PM – 9:00 PM). Cancellations within 24 hours forfeit downpayments.</li>
  <li><strong>Locker Security (T&amp;C 14):</strong> Use your assigned locker with the one-time passcode above. Passcode combination changes after every booking.</li>
 </ul>
 <p class="prf">Printed on ${new Date().toLocaleDateString("en-US",{year:"numeric",month:"long",day:"numeric"})}</p>`;
 window.print();
}

function buildReceiptFromData(tx, bk, customBal){
 if(!tx) return null;
 const rId = tx.numericId || tx.rawId || 1;
 const bId = (bk && (bk.rawId || bk.id)) ? (parseInt(String(bk.rawId||bk.id).replace(/\D/g,''),10)||1) : 1;
 const lockerNum = 'L-' + String(((bId * 7 + rId) % 24) + 1).padStart(2, '0');
 const passcode = String(((rId * 317 + bId * 13) % 9000) + 1000);
 return {
  receiptNumber: tx.receipt_number || tx.receiptNumber || ('REC-DEMO-' + String(tx.transaction_reference||tx.id||'20261111').slice(-6)),
  transactionReference: tx.transaction_reference || tx.transactionRef || tx.id || ('TXN-DEMO-' + rId),
  customerName: (U && U.fullName) || (C && C.fullName) || (bk && bk.name) || 'Customer',
  customerEmail: (U && U.email) || (C && C.email) || (bk && bk.email) || 'guest@example.com',
  bookingReference: bk ? bk.id : (tx.bookingId || ('BK-2026-' + String(bId).padStart(4,'0'))),
  serviceName: (bk && bk.serviceName) || tx.description || 'Spa Treatment',
  guestCount: (bk && bk.guests) || 1,
  appointmentDate: (bk && bk.date) || today(),
  appointmentTime: (bk && bk.time) || '14:00',
  paymentMethod: tx.payment_method_label || tx.paymentMethodLabel || tx.payment_method || 'GCash Demo',
  transactionType: tx.transactionType || tx.transaction_type || 'DOWNPAYMENT',
  amountPaid: tx.amount || 0,
  totalBookingAmount: bk ? bk.amount : (tx.amount || 0),
  remainingBalance: customBal !== undefined ? customBal : (bk ? (bk.remainingBalance !== undefined ? bk.remainingBalance : 0) : 0),
  transactionDate: tx.createdAt || new Date().toISOString(),
  lockerNumber: lockerNum,
  lockerPasscode: passcode,
  lockerPolicy: 'Per T&C 14, unique passcode changes combination after each booking.',
  disclaimer: 'DEMO PAYMENT RECEIPT — NO REAL MONEY WILL BE TRANSFERRED.'
 };
}

function openReceipt(rec){
 if(!rec) return;
 const isRefund = (rec.transactionType === "REFUND");
 open(isRefund ? "Digital Demo Refund Receipt" : "Digital Demo Receipt",`
  <div class="demo-banner">DEMO ${isRefund ? "REFUND" : "PAYMENT"} RECEIPT — NO REAL MONEY WILL BE TRANSFERRED.</div>
  <div class="rec-box">
   <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #ddd;padding-bottom:10px;margin-bottom:12px">
    <div><span class="eb" style="color:var(--gd);margin:0">Bella Vista Oasis Spa</span><h4 style="margin:2px 0 0;font-size:1.1rem">${esc(rec.receiptNumber)}</h4></div>
    <span class="badge ${isRefund ? 'REFUNDED' : 'PAID'}">${isRefund ? 'REFUNDED (DEMO)' : 'PAID (DEMO)'}</span>
   </div>
   <dl>
    <dt>Transaction Ref</dt><dd><b>${esc(rec.transactionReference)}</b></dd>
    <dt>Customer</dt><dd>${esc(rec.customerName)} (${esc(rec.customerEmail)})</dd>
    <dt>Booking Ref</dt><dd>${esc(rec.bookingReference)}</dd>
    <dt>Service</dt><dd>${esc(rec.serviceName)} (${rec.guestCount||1} guest${(rec.guestCount||1)>1?"s":""})</dd>
    <dt>Appointment</dt><dd>${fd(rec.appointmentDate)} at ${ft(rec.appointmentTime)}</dd>
    <dt>Payment Method</dt><dd><b>${esc(rec.paymentMethod)}</b></dd>
    <dt>Transaction Type</dt><dd>${esc((rec.transactionType||"").replace(/_/g," "))}</dd>
    <dt>${isRefund ? 'Amount Refunded' : 'Amount Paid'}</dt><dd><b style="color:var(--forest);font-size:1.1rem">${peso(rec.amountPaid)}</b></dd>
    <dt>Total Amount</dt><dd>${peso(rec.totalBookingAmount)}</dd>
    <dt>Remaining Balance</dt><dd><b>${peso(rec.remainingBalance)}</b></dd>
    <dt>Date & Time</dt><dd>${fd(rec.transactionDate.slice(0,10))} ${ft(rec.transactionDate.slice(11,16))}</dd>
   </dl>
   ${rec.lockerNumber?`
   <div style="background:#f4f7f4;border:1px solid #c8ddca;border-radius:4px;padding:10px 14px;margin:12px 0;display:flex;justify-content:space-between;align-items:center">
    <div>
     <span style="font-size:.72rem;letter-spacing:1px;text-transform:uppercase;color:#2e5033;font-weight:700">Digital Locker (T&amp;C 14)</span>
     <div style="font-size:1.05rem;font-weight:700;color:#1a3821">Locker: ${esc(rec.lockerNumber)}</div>
    </div>
    <div style="text-align:right">
     <span style="font-size:.72rem;letter-spacing:1px;text-transform:uppercase;color:#2e5033;font-weight:700">One-Time Passcode</span>
     <div style="font-size:1.25rem;font-weight:700;letter-spacing:3px;font-family:monospace;color:#1a3821">${esc(rec.lockerPasscode)}</div>
    </div>
   </div>
   <p class="note" style="margin:4px 0 10px;font-size:.78rem">Per T&amp;C 14, unique passcode changes combination after each booking.</p>`:''}
  </div>
  <div class="dr">
   <button class="btn line sm" data-a="close">Close</button>
   <button class="btn line sm" id="rec-pdf">Download Simulated PDF</button>
   <button class="btn sm" id="rec-prn" style="background:var(--forest);color:var(--iv)">Print Receipt</button>
  </div>
 `);
 const pr = $("#rec-prn"), pd = $("#rec-pdf");
 if(pr) pr.onclick=()=>printReceipt(rec);
 if(pd) pd.onclick=()=>printReceipt(rec);
}

async function openCheckout(bk){
 const pRes = await DB.getBookingPayment(bk.id);
 const pInfo = (pRes.ok && pRes.data && pRes.data.payment) ? pRes.data.payment : {
  totalAmount: bk.amount,
  paidAmount: bk.paidAmount || 0,
  downpaymentRequired: bk.downpaymentRequired || (bk.amount * 0.25),
  remainingBalance: bk.remainingBalance !== undefined ? bk.remainingBalance : bk.amount,
  downpaymentPercentage: 25,
  allowFullPayment: true,
  paymentStatus: bk.paymentStatus || "UNPAID"
 };

 const isUnpaid = pInfo.paymentStatus === "UNPAID";
 let selType = isUnpaid ? "DOWNPAYMENT" : "BALANCE_PAYMENT";
 let selMethod = "GCASH_DEMO";

 function renderCheckoutModal(){
  const payAmt = selType === "FULL_PAYMENT" ? pInfo.totalAmount : (selType === "DOWNPAYMENT" ? pInfo.downpaymentRequired : pInfo.remainingBalance);
  open("Demo Payment Checkout", `
   <div class="demo-banner">DEMO PAYMENT — NO REAL MONEY WILL BE TRANSFERRED.</div>
   <div style="background:#fff;border:1px solid rgba(197,164,109,.4);padding:14px;border-radius:4px;margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
     <strong style="font-size:1.05rem;color:var(--forest)">${esc(bk.serviceName)}</strong>
     <span class="badge ${bk.status}">${bk.status}</span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:.85rem;color:var(--mut)">
     <div>Date: <b style="color:var(--ink)">${fd(bk.date)}</b></div>
     <div>Time: <b style="color:var(--ink)">${ft(bk.time)}</b></div>
     <div>Guests: <b style="color:var(--ink)">${bk.guests||1}</b></div>
     <div>Total: <b style="color:var(--ink)">${peso(pInfo.totalAmount)}</b></div>
     <div>Already Paid: <b style="color:var(--ink)">${peso(pInfo.paidAmount)}</b></div>
     <div>Remaining Balance: <b style="color:var(--ink)">${peso(pInfo.remainingBalance)}</b></div>
    </div>
   </div>

   <h4 style="margin:10px 0 6px;font-size:1rem">Select Payment Amount</h4>
   <div style="display:grid;gap:8px;margin-bottom:14px">
    ${isUnpaid ? `
     <label style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:#fff;border:1px solid ${selType==='DOWNPAYMENT'?'var(--forest)':'#ddd'};border-radius:4px;cursor:pointer">
      <input type="radio" name="payType" value="DOWNPAYMENT" ${selType==='DOWNPAYMENT'?'checked':''}>
      <div><strong>Required Downpayment (${pInfo.downpaymentPercentage}%)</strong><div style="font-size:.84rem;color:var(--mut)">${peso(pInfo.downpaymentRequired)} to confirm reservation</div></div>
     </label>
     ${pInfo.allowFullPayment ? `
     <label style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:#fff;border:1px solid ${selType==='FULL_PAYMENT'?'var(--forest)':'#ddd'};border-radius:4px;cursor:pointer">
      <input type="radio" name="payType" value="FULL_PAYMENT" ${selType==='FULL_PAYMENT'?'checked':''}>
      <div><strong>Full Payment (100%)</strong><div style="font-size:.84rem;color:var(--mut)">${peso(pInfo.totalAmount)} pay total treatment in advance</div></div>
     </label>` : ''}
    ` : `
     <div style="padding:10px 12px;background:#fff;border:1px solid var(--forest);border-radius:4px">
      <strong>Remaining Balance</strong>: <span style="font-size:1.1rem;color:var(--forest);font-weight:700">${peso(pInfo.remainingBalance)}</span>
     </div>
    `}
   </div>

   <h4 style="margin:12px 0 6px;font-size:1rem">Select Demo Payment Method</h4>
   <div class="pay-grid">
    <div class="pay-card ${selMethod==='GCASH_DEMO'?'active':''}" data-m="GCASH_DEMO">
     <strong>GCash Demo</strong>
     <span class="pay-sub">Simulated mobile wallet</span>
    </div>
    <div class="pay-card ${selMethod==='MAYA_DEMO'?'active':''}" data-m="MAYA_DEMO">
     <strong>Maya Demo</strong>
     <span class="pay-sub">Simulated digital wallet</span>
    </div>
    <div class="pay-card ${selMethod==='CASH_AT_SPA'?'active':''}" data-m="CASH_AT_SPA">
     <strong>Cash at Spa</strong>
     <span class="pay-sub">Pay at spa desk (Pending)</span>
    </div>
    <div class="pay-card ${selMethod==='BANK_TRANSFER_DEMO'?'active':''}" data-m="BANK_TRANSFER_DEMO">
     <strong>Bank Transfer Demo</strong>
     <span class="pay-sub">Simulated bank payment</span>
    </div>
   </div>

   <div style="margin:14px 0 8px">
    <label style="display:flex;align-items:center;gap:8px;font-size:.84rem;color:var(--mut);cursor:pointer">
     <input type="checkbox" id="chk-fail"> Simulate payment failure (test error handling)
    </label>
   </div>

   <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #ddd;padding-top:14px;margin-top:10px;flex-wrap:wrap;gap:10px">
    <div><span style="font-size:.84rem;color:var(--mut)">Amount to Pay:</span> <b style="font-size:1.25rem;color:var(--forest)">${peso(payAmt)}</b></div>
    <div style="display:flex;gap:8px;align-items:center">
     <button type="button" class="btn line sm" data-a="close">Cancel</button>
     <button class="btn sm" id="btn-proceed" style="background:var(--forest);color:var(--iv)">Continue to Payment &rarr;</button>
    </div>
   </div>
  `);

  document.querySelectorAll("[name=payType]").forEach(r=>{
   r.onchange=()=>{ selType=r.value; renderCheckoutModal(); };
  });

  document.querySelectorAll(".pay-card").forEach(c=>{
   c.onclick=()=>{ selMethod=c.dataset.m; renderCheckoutModal(); };
  });

  const btnP = $("#btn-proceed");
  if(btnP) btnP.onclick=async()=>{
   const simFail = $("#chk-fail") ? $("#chk-fail").checked : false;
   if(selMethod === "CASH_AT_SPA"){
    const res = await DB.processDemoPayment(bk.id, {
     paymentMethod: "CASH_AT_SPA",
     paymentType: selType,
     simulateFailure: false
    });
    if(res.ok){
     await DB.loadPortal();
     C = DB.customer();
     render();
     open("Demo Cash Reservation Recorded", `
      <div class="demo-banner">DEMO PAYMENT — NO REAL MONEY WILL BE TRANSFERRED.</div>
      <div class="pay-chk" style="background:#fef3c7;color:#92400e;font-size:1.8rem">₱</div>
      <h3 style="text-align:center;color:#92400e">Pay at Spa Recorded</h3>
      <p style="text-align:center">Your request to pay <strong>${peso(payAmt)}</strong> at the spa desk has been submitted.</p>
      <div style="background:#fff;border:1px solid #ddd;padding:12px;border-radius:4px;margin:12px 0;font-size:.88rem">
       <div>Booking: <b>${esc(bk.id)}</b></div>
       <div>Status: <span class="badge PENDING">PENDING</span></div>
       <div>Amount: <b>${peso(payAmt)}</b></div>
       <p class="note" style="margin:6px 0 0">Please pay at the front desk upon arrival. An administrator will confirm your payment.</p>
      </div>
      <div class="dr"><button class="btn line sm" data-a="close">Close</button></div>
     `);
    } else {
     Notice.error("Reservation Error", res.data.error || "Could not record cash reservation.");
    }
    return;
   }

   const statusSteps = [
    "Connecting to demo payment provider...",
    "Simulating secure verification...",
    "Confirming demo transaction..."
   ];
   let stepIdx = 0;

   let cancelled = false;
   const onAbort = () => { cancelled = true; clearInterval(interval); };
   dlg.addEventListener("close", onAbort, { once: true });

   open("Processing Demo Payment", `
    <div class="demo-banner">DEMO PAYMENT — NO REAL MONEY WILL BE TRANSFERRED.</div>
    <div class="pay-spin"></div>
    <h3 style="text-align:center" id="pay-prog-msg">${statusSteps[0]}</h3>
    <p style="text-align:center;color:var(--mut);font-size:.88rem">Please wait while the simulation completes...</p>
    <div style="text-align:center;margin-top:14px"><button type="button" class="btn line sm" data-a="close">Cancel Demo Payment</button></div>
   `);

   const interval = setInterval(()=>{
    stepIdx++;
    if(stepIdx < statusSteps.length){
     const el = $("#pay-prog-msg");
     if(el) el.textContent = statusSteps[stepIdx];
    }
   }, 700);

   setTimeout(async()=>{
    clearInterval(interval);
    if(cancelled || !dlg.open) return;
    const res = await DB.processDemoPayment(bk.id, {
     paymentMethod: selMethod,
     paymentType: selType,
     simulateFailure: simFail
    });

    if(res.ok && res.data && res.data.transaction && res.data.transaction.status === "PAID"){
     await DB.loadPortal();
     C = DB.customer();
     render();
     const tx = res.data.transaction;
     const remBal = (res.data.paymentSummary && res.data.paymentSummary.remainingBalance !== undefined)
       ? res.data.paymentSummary.remainingBalance
       : (res.data.remainingBalance !== undefined ? res.data.remainingBalance : 0);
     const txRef = tx.transaction_reference || tx.transactionRef || tx.id;
     const txId = tx.numericId || tx.rawId || tx.id;

     open("Demo Payment Successful", `
      <div class="demo-banner">DEMO PAYMENT — NO REAL MONEY WILL BE TRANSFERRED.</div>
      <div class="pay-chk">✓</div>
      <h3 style="text-align:center;color:#166534">Payment Completed!</h3>
      <p style="text-align:center">Simulated demo payment of <strong>${peso(tx.amount)}</strong> was successful.</p>
      <div style="background:#fff;border:1px solid #ddd;padding:12px;border-radius:4px;margin:12px 0;font-size:.88rem">
       <div>Transaction Ref: <b>${esc(txRef)}</b></div>
       <div>Method: <b>${esc(tx.payment_method_label||tx.paymentMethodLabel||tx.payment_method)}</b></div>
       <div>Receipt No: <b>${esc(tx.receipt_number||tx.receiptNumber||'Generated')}</b></div>
       <div>New Remaining Balance: <b>${peso(remBal)}</b></div>
      </div>
      <div class="dr">
       <button class="btn line sm" data-a="close">Close</button>
       <button class="btn sm" id="succ-rec" data-a="rec" data-i="${esc(txId)}" style="background:var(--forest);color:var(--iv)">View Digital Receipt</button>
      </div>
     `);
     const sRec = $("#succ-rec");
     if(sRec){
      sRec.onclick=async(ev)=>{
       if(ev) ev.preventDefault();
       const directRec = (res.data && res.data.receipt) || buildReceiptFromData(tx, bk, remBal);
       if(directRec){
        openReceipt(directRec);
        return;
       }
       const rRes = await DB.getReceipt(txId);
       if(rRes.ok && rRes.data && rRes.data.receipt) openReceipt(rRes.data.receipt);
       else openReceipt(buildReceiptFromData(tx, bk, remBal));
      };
     }
    } else {
     open("Simulated Payment Failed", `
      <div class="demo-banner">DEMO PAYMENT — NO REAL MONEY WILL BE TRANSFERRED.</div>
      <div class="pay-chk" style="background:#fee2e2;color:#991b1b">✕</div>
      <h3 style="text-align:center;color:#991b1b">Payment Simulation Failed</h3>
      <p style="text-align:center">${esc((res.data&&res.data.error)||"The simulated payment was declined.")}</p>
      <p class="note" style="text-align:center">No money was transferred. Your booking status remains unchanged.</p>
      <div class="dr">
       <button class="btn line sm" data-a="close">Close</button>
       <button class="btn sm" id="pay-retry">Try Again</button>
      </div>
     `);
     const ret = $("#pay-retry");
     if(ret) ret.onclick=()=>renderCheckoutModal();
    }
   }, 2100);
  };
 }
 renderCheckoutModal();
}

const dlg=$("#dlg"),open=(t,h)=>{
 $("#dt").textContent=t;
 $("#db").innerHTML=h;
 if(dlg&&!dlg.open){
  try{ dlg.showModal(); }catch(err){ console.warn(err); }
 }
};
document.addEventListener("click",async e=>{const b=e.target.closest("[data-a]");if(!b)return;
 const{a,i,v}=b.dataset;
 const bkList=DB.bookings(C?C.id:null)||[];
 const bk=i&&bkList.find(x=>x.id===i||String(x.id)===String(i)||x.bookingRef===i||String(x.rawId)===String(i)||(String(i).startsWith('BK-')&&x.id&&x.id.endsWith(String(i).replace(/^BK-(?:\d{4}-)?0*/,'')))||(x.id&&String(x.id).replace(/\D/g,'')===String(i).replace(/\D/g,'')));
 if(a==="f"){flt=v;render()}else if(a==="tf"){tf=v;render()}
 else if(a==="pay"&&bk){openCheckout(bk)}
 else if(a==="rec"&&i){
  const txList=DB.txns(C?C.id:null)||[];
  const matchTx=txList.find(t=>t.id===i||t.transactionRef===i||String(t.numericId)===String(i)||String(t.rawId)===String(i));
  const r=await DB.getReceipt(i);
  if(r.ok&&r.data&&r.data.receipt) openReceipt(r.data.receipt);
  else if(matchTx){
   const matchBk=(DB.bookings(C?C.id:null)||[]).find(b=>b.id===matchTx.bookingId||String(b.rawId)===String(matchTx.rawBookingId));
   openReceipt(buildReceiptFromData(matchTx, matchBk));
  } else Notice.warning("Digital Receipt", (r.data&&r.data.error)||"Digital receipt unavailable.");
 }
 else if(a==="bk-rec"&&bk){
  const txList=(DB.txns(C?C.id:null)||[]).filter(t=>(t.bookingId===bk.id||String(t.rawBookingId)===String(bk.rawId)||String(t.rawBookingId)===String(bk.id).replace(/\D/g,'')||String(t.bookingId).replace(/\D/g,'')===String(bk.id).replace(/\D/g,''))&&t.status==="PAID");
  if(txList.length>0){
   const tid=txList[0].numericId||txList[0].rawId||txList[0].id||txList[0].transactionRef;
   const r=await DB.getReceipt(tid);
   if(r.ok&&r.data&&r.data.receipt) openReceipt(r.data.receipt);
   else openReceipt(buildReceiptFromData(txList[0], bk));
  } else {
   Notice.info("No Receipts", "No confirmed payment receipts found for this booking.");
  }
 }
 else if(a==="view"&&bk){
  const fin = getBookingFinancials(bk);
  let balText = "";
  if(!fin.isOutstandingApplicable){
    if(fin.paymentStatus === "REFUNDED") balText = `<span style="color:#2e7d32;font-weight:600">None (Fully Refunded)</span>`;
    else if(fin.paymentStatus === "FORFEITED") balText = `<span style="color:#b91c1c;font-weight:600">None (Forfeited)</span>`;
    else balText = `<span style="color:#666;font-weight:600">None (Cancelled)</span>`;
  } else if(fin.outstanding === 0){
    balText = `<span style="color:#2e7d32;font-weight:600">₱0.00 (Fully Paid)</span>`;
  } else {
    balText = `<b>${peso(fin.outstanding)}</b>`;
  }

  const hasPaid = fin.totalPaid > 0;
  const canPayNow = fin.canPay;

  let dlContent = `
    <dt>Booking ID</dt><dd><b>${esc(bk.id)}</b></dd>
    <dt>Service</dt><dd>${esc(bk.serviceName)}</dd>
    <dt>Assigned Suite</dt><dd>${esc(bk.roomName || "Spa Suite")}</dd>
    <dt>Date</dt><dd>${fd(bk.date)}</dd>
    <dt>Time</dt><dd>${ft(bk.time)}</dd>
    <dt>Duration</dt><dd>${bk.duration ? esc(bk.duration) + " min" : "—"}</dd>
    <dt>Guests</dt><dd>${bk.guests || 1}</dd>
    <dt>Total Price</dt><dd><b>${price(bk)}</b></dd>
    <dt>Payment Status</dt><dd><span class="badge ${fin.paymentStatus}">${fin.paymentStatus.replace(/_/g, " ")}</span></dd>
    <dt>Amount Paid</dt><dd><b>${peso(fin.totalPaid)}</b></dd>
  `;

  if (fin.totalRefunded > 0) {
    dlContent += `
      <dt>Amount Refunded</dt><dd><b style="color:#2e7d32">${peso(fin.totalRefunded)} (Simulated Demo Refund)</b></dd>
      <dt>Net Retained</dt><dd><b>${peso(fin.netPaid)}</b></dd>
    `;
  }

  dlContent += `
    <dt>Outstanding Balance</dt><dd>${balText}</dd>
    <dt>Status</dt><dd><span class="badge ${bk.status}">${bk.status}</span></dd>
    <dt>Booking date</dt><dd>${fd(bk.createdAt.slice(0, 10))}</dd>
    ${bk.cancelReason ? `<dt>Cancel reason</dt><dd>${esc(bk.cancelReason)}</dd>` : ""}
    ${bk.cancelledBy ? `<dt>Cancelled by</dt><dd>${esc(bk.cancelledBy)}${bk.cancelledAt ? " (" + fdt(bk.cancelledAt) + ")" : ""}</dd>` : ""}
    ${bk.originalDate ? `<dt>Original schedule</dt><dd>${fd(bk.originalDate)} at ${ft(bk.originalTime)}</dd>` : ""}
    <dt>Customer</dt><dd>${esc(U.fullName)}<br>${esc(U.email)}<br>${esc(C.phone || "")}</dd>
  `;

  open("Reservation Details", `<dl>${dlContent}</dl>
    <div class="dr">
      ${hasPaid ? `<button class="btn line sm" data-a="bk-rec" data-i="${esc(bk.id)}">View Digital Receipt</button>` : ""}
      ${canPayNow ? `<button class="btn sm" data-a="pay" data-i="${esc(bk.id)}" style="background:var(--forest);color:var(--iv)">${fin.paymentStatus === 'UNPAID' ? 'Pay Downpayment' : 'Pay Balance'}</button>` : ""}
      ${canPrint(bk) ? `<button class="btn line sm" data-a="pr" data-i="${esc(bk.id)}">Print confirmation</button>` : ""}
      <button class="btn line sm" data-a="close">Close</button>
    </div>`
  );
 }
 else if(a==="cancel"&&bk){
  const apptT=new Date(bk.date+"T"+(bk.time.length===5?bk.time+":00":bk.time)+"+08:00").getTime();
  const diffH=(apptT-Date.now())/3600000;
  if(diffH<24){
   open("Cancellation Notice",`<div style="padding:4px 0">
     <div style="width:48px;height:48px;border-radius:50%;background:#fde8e8;color:#9b1c1c;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:24px;border:2px solid #f8b4b4">!</div>
     <h3 style="text-align:center;color:#9b1c1c;margin:0 0 8px">Late Cancellation Notice</h3>
     <p style="font-size:.9rem;line-height:1.5;color:#333;margin:0 0 10px">Per Bella Vista Oasis Spa business policy, cancellations must be made at least <strong>24 hours</strong> prior to your appointment (Asia/Manila time). Your appointment is scheduled in <strong>${diffH>0?diffH.toFixed(1)+" hours":"the past"}</strong>.</p>
     <div style="background:#fff8e6;border:1px solid #fed7aa;border-radius:6px;padding:12px;font-size:.84rem;color:#7c2d12;margin:10px 0">
       <strong>Policy Notice (T&amp;C 02):</strong> Online cancellation is closed within the 24-hour restriction window. Any downpayment made is forfeited. For emergency rescheduling inquiries, please contact the spa front desk directly.
     </div>
     <div class="dr"><button class="btn line sm" data-a="close">Close</button></div>
   </div>`);
  }else{
   const paidAmt=Number(bk.paidAmount||0);
   const rBal=(bk.remainingBalance!==undefined)?bk.remainingBalance:(bk.amount-paidAmt);
   open("Cancel Appointment?",`
    <p style="margin:0 0 12px;font-size:.92rem;color:var(--ink)">Are you sure you want to cancel this booking? This may affect its payment and reservation status.</p>
    
    <div style="background:var(--sand,#F7F4EE);border:1px solid #d5cbaf;border-radius:6px;padding:12px 14px;margin-bottom:14px">
      <div style="font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;color:var(--forest,#203A2A);margin-bottom:8px">Appointment Details</div>
      <table style="width:100%;font-size:.86rem;border-collapse:collapse">
        <tr><td style="color:#666;padding:3px 0;width:40%">Booking Ref:</td><td><b>${esc(bk.id)}</b></td></tr>
        <tr><td style="color:#666;padding:3px 0">Customer:</td><td>${esc(U.fullName)}</td></tr>
        <tr><td style="color:#666;padding:3px 0">Treatment:</td><td><b>${esc(bk.serviceName)}</b></td></tr>
        <tr><td style="color:#666;padding:3px 0">Date &amp; Time:</td><td>${fd(bk.date)} at ${ft(bk.time)}</td></tr>
        <tr><td style="color:#666;padding:3px 0">Assigned Suite:</td><td>${esc(bk.roomName||"Executive Suite")}</td></tr>
        <tr><td style="color:#666;padding:3px 0">Guests:</td><td>${bk.guests||1} guest(s)</td></tr>
        <tr><td style="color:#666;padding:3px 0">Booking Status:</td><td><span class="badge ${bk.status}">${bk.status}</span></td></tr>
        <tr><td style="color:#666;padding:3px 0">Payment Status:</td><td><span class="badge ${bk.paymentStatus||"UNPAID"}">${(bk.paymentStatus||"UNPAID").replace(/_/g," ")}</span></td></tr>
        <tr><td style="color:#666;padding:3px 0">Amount Paid:</td><td><strong style="color:var(--forest,#203A2A)">${peso(paidAmt)}</strong></td></tr>
      </table>
    </div>

    ${paidAmt>0?`
      <div style="background:#eef7ee;border:1px solid #c2e2c4;border-radius:6px;padding:12px 14px;margin-bottom:14px">
        <div style="color:#205e26;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:6px">
          <span>✓</span> Eligible for Simulated Demo Refund: ${peso(paidAmt)}
        </div>
        <p style="margin:0;font-size:.82rem;color:#284e2c;line-height:1.45">
          Because you are cancelling at least 24 hours prior to your scheduled session, your simulated payment of <strong>${peso(paidAmt)}</strong> will be credited back immediately as an instant demo refund (~3-second processing). <em>(Demo simulation only — no real financial transactions or bank transfers occur).</em>
        </p>
      </div>`:
      `<div style="background:#f4f6f4;border:1px solid #d0dcd0;border-radius:6px;padding:10px 12px;font-size:.82rem;color:#456048;margin-bottom:14px">
        <strong>Cancellation Policy:</strong> Cancellations made at least 24 hours in advance incur no penalty and immediately release the reserved treatment room.
      </div>`}

    <fieldset style="border:0;padding:0;margin:0 0 14px">
      <legend style="font-weight:600;font-size:.85rem;color:var(--forest,#203A2A);margin-bottom:8px">Please select a cancellation reason:</legend>
      <div style="display:flex;flex-direction:column;gap:6px">
        ${["Schedule conflict","Change of plans","Found another appointment time","Personal reason","Other"].map((r,n)=>`
          <label class="rd" style="display:flex;align-items:center;gap:8px;font-size:.88rem;cursor:pointer">
            <input type="radio" name="rs" value="${r}"${n===0?" checked":""} onchange="document.getElementById('other_reason_box').style.display=this.value==='Other'?'block':'none'">
            <span>${r}</span>
          </label>
        `).join("")}
      </div>
      <div id="other_reason_box" style="display:none;margin-top:8px">
        <input type="text" id="other_reason_txt" placeholder="Please specify your reason (optional)" maxlength="200" style="width:100%;box-sizing:border-box;padding:8px 10px;font:inherit;border:1px solid #c9bfa4;border-radius:3px">
      </div>
    </fieldset>

    <div class="dr">
      <button class="btn line sm" data-a="close">Go Back</button>
      <button class="btn sm dg" data-a="doc" data-i="${esc(bk.id)}">Confirm Cancellation</button>
    </div>
   `);
  }
 }
 else if(a==="doc"&&bk&&up(bk)){
  b.disabled=true;
  b.textContent="Cancelling...";
  const selectedRadio = document.querySelector("[name=rs]:checked");
  let reason = selectedRadio ? selectedRadio.value : "Customer cancelled online";
  if(reason === "Other"){
    const otherTxt = document.getElementById("other_reason_txt");
    if(otherTxt && otherTxt.value.trim()){
      reason = "Other: " + otherTxt.value.trim();
    }
  }
  const paidAmt = (bk.paidAmount !== undefined) ? Number(bk.paidAmount) : (bk.depositPaid || 0);

  if (paidAmt > 0) {
    const refundSteps = [
      "Verifying 24-hour cancellation policy...",
      "Connecting to simulated demo refund gateway...",
      "Crediting demo balance to account (~3s demo)..."
    ];
    let stepIdx = 0;
    open("Processing Simulated Demo Refund", `
      <div class="demo-banner">DEMO REFUND — NO REAL MONEY WILL BE TRANSFERRED.</div>
      <div class="pay-spin"></div>
      <h3 style="text-align:center" id="refund-prog-msg">${refundSteps[0]}</h3>
      <p style="text-align:center;color:var(--mut);font-size:.88rem">Simulating instant demo refund in approximately 3 seconds...</p>
    `);
    const interval = setInterval(() => {
      stepIdx++;
      if (stepIdx < refundSteps.length) {
        const el = $("#refund-prog-msg");
        if (el) el.textContent = refundSteps[stepIdx];
      }
    }, 700);

    setTimeout(async () => {
      clearInterval(interval);
      const r = await DB.cancelBooking(i, reason);
      dlg.close();
      render();
      if (r.ok) {
        if (r.data && r.data.refund && Number(r.data.refund.amount) > 0) {
          const rf = r.data.refund;
          open("Cancellation & Simulated Refund Processed", `
            <div style="text-align:center;padding:6px 0 14px">
              <div style="width:52px;height:52px;border-radius:50%;background:#e8f5e9;color:#2e7d32;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:26px;border:2px solid #a5d6a7">✓</div>
              <h3 style="margin:0 0 6px;color:var(--forest,#203A2A);font-size:1.35rem">Appointment Cancelled</h3>
              <p class="note" style="margin:0">Booking <strong>#${esc(bk.id)}</strong> (${esc(bk.serviceName)}) has been successfully cancelled.</p>
            </div>
            <div style="background:var(--sand,#F7F4EE);border:1px solid #c9bfa4;border-radius:6px;padding:14px 16px;margin-bottom:14px">
              <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(0,0,0,0.08)">
                <span style="color:#555">Simulated Refund Amount</span>
                <strong style="color:var(--forest,#203A2A);font-size:1.25rem">${esc(rf.formattedAmount || peso(rf.amount))}</strong>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(0,0,0,0.08)">
                <span style="color:#555">Refund Method</span>
                <strong style="color:#203A2A">${esc(rf.paymentMethodLabel || rf.paymentMethod)} (Demo)</strong>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(0,0,0,0.08)">
                <span style="color:#555">Demo Transaction Ref</span>
                <span style="font-family:monospace;font-size:.85rem;color:#333">${esc(rf.transactionRef)}</span>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid rgba(0,0,0,0.08)">
                <span style="color:#555">Processing Time</span>
                <strong style="color:#2e7d32">Instant Demo Credit (~3s)</strong>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0">
                <span style="color:#555">Updated Payment Status</span>
                <span class="badge PAID" style="background:#2e7d32;color:#fff;padding:2px 8px;border-radius:4px;font-size:.72rem">REFUNDED (DEMO)</span>
              </div>
            </div>
            <div style="background:#f0f4f0;border:1px solid #c8d8c8;border-radius:6px;padding:12px 14px;margin-bottom:16px">
              <div style="font-weight:600;color:var(--forest,#203A2A);margin-bottom:4px;display:flex;align-items:center;gap:6px">
                <span>✓</span> <strong>Simulated Refund Notice</strong>
              </div>
              <p style="margin:0;font-size:.83rem;color:#2e4432;line-height:1.45">
                Your simulated demo refund of <strong>${esc(rf.formattedAmount || peso(rf.amount))}</strong> was processed and credited in approximately 3 seconds. <em>All payments and refunds on this website are local simulations for demonstration purposes only; no real money or bank accounts are debited or credited.</em>
              </p>
            </div>
            <div class="dr">
              <button class="btn sm" data-a="close" style="width:100%">OK, Done</button>
            </div>
          `);
        } else {
          Notice.toast("success", "Appointment #" + bk.id + " cancelled successfully.");
        }
      } else {
        Notice.error("Cancellation Failed", (r.data && r.data.error) || "Could not cancel this booking.");
      }
    }, 2200);
  } else {
    const r = await DB.cancelBooking(i, reason);
    dlg.close();
    render();
    if (r.ok) {
      Notice.toast("success", "Appointment #" + bk.id + " cancelled successfully.");
    } else {
      Notice.error("Cancellation Failed", (r.data && r.data.error) || "Could not cancel this booking.");
    }
  }
 }
 else if(a==="hide"&&bk){
  const ok=await Notice.confirm({
   title:"Remove from Your List?",
   message:"Remove this booking from your list? The spa keeps its record in their system.",
   confirmLabel:"Remove",
   cancelLabel:"Keep in List",
   danger:true
  });
  if(ok){
   const r=await DB.hideBooking(i);
   if(r.ok){
    Notice.toast("success","Booking removed from your view.");
    render();
   }else{
    Notice.error("Remove Booking Failed",r.data.error||"Could not remove this booking.");
   }
  }
 }
 else if(a==="rsq"&&bk&&up(bk)){
  const apptT=new Date(bk.date+"T"+(bk.time.length===5?bk.time+":00":bk.time)+"+08:00").getTime();
  const diffH=(apptT-Date.now())/3600000;
  if(diffH<24){
   open("Reschedule Notice",`<p style="color:#8a2f2f;font-weight:600">Rescheduling Window Closed</p><p>Per Bella Vista Oasis Spa policy, rescheduling requests must be submitted at least <strong>24 hours</strong> prior to your original appointment time. Your appointment is scheduled in <strong>${diffH>0?diffH.toFixed(1)+" hours":"the past"}</strong>.</p><p class="note">Online rescheduling is closed. Please contact front desk staff directly.</p><div class="dr"><button class="btn line sm" data-a="close">Close</button></div>`);
  }else{
   open("Request a New Time",`<p class="note" style="margin:0 0 14px">Current booking: <b>${fd(bk.date)}, ${ft(bk.time)}</b> (${bk.duration||60} min, ${bk.guests||1} guests). The spa must approve the change. Until then your current time stays. Requests must be submitted at least 24 hours prior to original appointment.</p><form id="rf" novalidate><div class="f"><label for="rd">New date</label><input id="rd" type="date" min="${today()}"></div><div class="f"><label for="rt">New time</label><select id="rt" style="width:100%"><option value="">Choose a date first</option></select><p class="note" id="rh" style="margin:6px 0 0"></p></div><div class="f"><label for="rr">Reason (optional)</label><input id="rr" type="text" maxlength="300"></div><p class="er" id="re" role="alert"></p><div class="dr"><button type="button" class="btn line sm" data-a="close">Cancel</button><button class="btn sm" type="submit">Send request</button></div></form>`);
   let seq=0;$("#rd").addEventListener("change",async()=>{const d=$("#rd").value,t=$("#rt"),h=$("#rh");h.textContent="";if(!d||d<today()){t.innerHTML='<option value="">Choose a date first</option>';return}
    const n=++seq;t.innerHTML='<option value="">Loading times...</option>';const r=await Auth.api("/api/availability?date="+encodeURIComponent(d)+"&service="+encodeURIComponent(bk.serviceName)+"&duration="+encodeURIComponent(bk.duration||60)+"&exclude="+encodeURIComponent(bk.id));if(n!==seq)return;
    if(!r.ok){t.innerHTML='<option value="">Times unavailable</option>';h.textContent=(r.data&&r.data.error)||"Could not load available times.";return}
    if(!r.data.open){t.innerHTML='<option value="">Closed on this date</option>';h.textContent="The spa is closed on this date.";return}
    const sl=r.data.slots,free=sl.filter(x=>x.free);t.innerHTML='<option value="">'+(free.length?"Choose a time":"Fully booked on this date")+"</option>"+sl.map(x=>`<option value="${x.time}"${x.free?"":" disabled"}>${f12(x.time)}${x.free?"":" (fully booked)"}</option>`).join("");
    h.textContent="Operating hours: "+f12(r.data.opens)+" to "+f12(r.data.closes)+". Treatment: "+(r.data.minutes||bk.duration||60)+" min (+ 30-min cleaning buffer).";
   });
   $("#rf").addEventListener("submit",async ev=>{
    ev.preventDefault();
    const d=$("#rd").value,t=$("#rt").value,er=$("#re");
    er.textContent="";
    if(!d||!t){er.textContent="Choose a new date and time.";return}
    if(window.Notice&&Notice.confirm){
     const ok=await Notice.confirm({
      title:"Send Reschedule Request?",
      message:`Request to move booking #${esc(bk.id)} (${esc(bk.serviceName)}) to ${fd(d)} at ${f12(t)}? The spa will review your request.`,
      confirmLabel:"Send Request",
      cancelLabel:"Cancel"
     });
     if(!ok)return;
    }
    const r=await DB.requestReschedule(bk.id,d,t,$("#rr").value.trim());
    if(!r.ok){er.textContent=r.data.error||"Could not send the request.";return}
    dlg.close();render();
    if(window.Notice&&Notice.toast)Notice.toast("success","Reschedule request sent. The spa will review it.");
   });
  }
 }
 else if(a==="rwd"){
  const ok=await Notice.confirm({
   title:"Withdraw Request?",
   message:"Are you sure you want to withdraw your request to reschedule this appointment?",
   confirmLabel:"Withdraw Request",
   cancelLabel:"Keep Request",
   danger:true
  });
  if(ok){
   const r=await DB.withdrawReschedule(i);
   render();
   if(r.ok) Notice.toast("success","Reschedule request withdrawn.");
   else Notice.error("Withdraw Failed",(r.data&&r.data.error)||"Could not withdraw the request.");
  }
 }
 else if(a==="rvq"&&bk&&bk.status==="COMPLETED"&&!bk.review){open("How was your visit?",`<p class="note" style="margin:0 0 10px">${esc(bk.serviceName)} on ${fd(bk.date)}</p><form id="vf" novalidate><fieldset style="border:0;padding:0;margin:0 0 14px"><legend class="note" style="margin-bottom:6px">Your rating</legend><div class="rate">${[5,4,3,2,1].map(n=>`<input type="radio" name="rt" id="st${n}" value="${n}"><label for="st${n}" title="${n} star${n>1?"s":""}"><span class="sr">${n} star${n>1?"s":""}</span>\u2605</label>`).join("")}</div></fieldset><div class="f"><label for="vc">Comment (optional)</label><textarea id="vc" rows="4" maxlength="1000" style="width:100%;font:inherit;padding:11px 12px;border:1px solid #c9bfa4;border-radius:2px"></textarea></div><p class="note" style="margin:0 0 6px">If approved by the spa, your review may appear on our website with your first name and last initial.</p><p class="er" id="ve" role="alert"></p><div class="dr"><button type="button" class="btn line sm" data-a="close">Not now</button><button class="btn sm" type="submit">Submit review</button></div></form>`);
  $("#vf").addEventListener("submit",async ev=>{ev.preventDefault();const c=document.querySelector("[name=rt]:checked");if(!c){$("#ve").textContent="Please choose a rating from 1 to 5 stars.";return}
   const r=await DB.submitReview(bk.id,Number(c.value),$("#vc").value.trim());if(!r.ok){$("#ve").textContent=r.data.error||"Could not save your review.";return}dlg.close();render();flash(true,"Thank you for your review!")})}
 else if(a==="pr"&&bk&&canPrint(bk)){dlg.close();printBk(bk)}
 else if(a==="close")dlg.close();
 else if(a==="edit"){open("Edit Profile",`<form id="ef" novalidate><div class="f"><label for="e1">Full name</label><input id="e1" type="text" value="${esc(U.fullName)}" required></div><div class="f"><label for="e2">Phone number</label><input id="e2" type="tel" value="${esc(C.phone||"")}" placeholder="09XX XXX XXXX" maxlength="13"><p class="note" style="margin:4px 0 0;font-size:.78rem">Format: 11 digits (09…) or 12 digits (639…)</p></div><div class="f"><label for="e3">Birthday</label><input id="e3" type="date" value="${esc(C.birthday)}" max="${today()}"></div><div class="f"><label for="e4">Preferred contact method</label><select id="e4" style="width:100%">${["","Email","Phone call","SMS"].map(o=>`<option value="${o}"${C.contact===o?" selected":""}>${o||"Not set"}</option>`).join("")}</select></div><p class="er" id="ee" role="alert"></p><div class="dr"><button type="button" class="btn line sm" data-a="close">Cancel</button><button class="btn sm" type="submit">Save changes</button></div></form>`);
  const e2=$("#e2");if(e2)formatPhoneInput(e2);
  $("#ef").addEventListener("submit",async ev=>{ev.preventDefault();const pVal=$("#e2").value.trim();if(pVal&&!isValidPhone(pVal)){$("#ee").textContent="Phone number must contain 11 digits (09…) or 12 digits (639…).";return}const r=await DB.updateProfile({fullName:$("#e1").value,phone:pVal?formatPhone(pVal):"",birthday:$("#e3").value,contact:$("#e4").value});if(!r.ok){$("#ee").textContent=r.data.error||"Could not save your changes.";return}init();dlg.close();render();const m=$("#pm");if(m){m.className="ok";m.textContent="\u2713 Profile updated successfully."}})}
 else if(a==="fav"){await DB.toggleFav(v);render()}
 else if(a==="read"){await DB.markRead();render()}
 else if(a==="refresh-portal"){
  await reloadPortal(true, b);
 }
 else if(a==="pref"){const p=Object.assign({},C.preferences);p[v]=b.checked;const r=await DB.savePrefs(p);if(r.ok)C=DB.customer();else b.checked=!b.checked}});
document.addEventListener("input",e=>{if(e.target.id==="tq"){tq=e.target.value;const p=e.target.selectionStart;render();const n=$("#tq");n.focus();n.setSelectionRange(p,p)}});
document.addEventListener("change",e=>{
  if(e.target.id==="srt"){
    srt=e.target.value;
    try { sessionStorage.setItem("bvn_srt", srt); } catch(err){}
    render();
  }
});

let isRefreshing = false;
let portalReqSeq = 0;

async function reloadPortal(showToast = false, btn = null){
  if (isRefreshing && btn) return false;
  
  let originalBtnHtml = "";
  if (btn) {
    btn.disabled = true;
    originalBtnHtml = btn.innerHTML;
    btn.innerHTML = `<span style="display:inline-block;animation:spin 1s linear infinite">&#x21BB;</span> Refreshing...`;
  }
  
  isRefreshing = true;
  const currentSeq = ++portalReqSeq;
  
  try {
    const r = await DB.loadPortal();
    // Discard stale response if a newer refresh was triggered
    if (currentSeq !== portalReqSeq) return false;
    
    if (r && r.ok) {
      init();
      render();
      if (showToast) {
        if (window.Notice && Notice.toast) {
          Notice.toast("success", "Reservations updated.");
        } else {
          flash(true, "Reservations updated.");
        }
      }
      return true;
    } else {
      const err = (r && r.data && r.data.error) || "Could not refresh reservations. Please try again.";
      if (showToast) {
        if (window.Notice && Notice.error) {
          Notice.error("Refresh Failed", err);
        } else {
          flash(false, "", err);
        }
      }
      return false;
    }
  } catch (ex) {
    if (showToast) {
      if (window.Notice && Notice.error) {
        Notice.error("Refresh Failed", "A network error occurred while refreshing reservations.");
      } else {
        flash(false, "", "Network error refreshing reservations.");
      }
    }
    return false;
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalBtnHtml;
    }
    isRefreshing = false;
  }
}

addEventListener("hashchange",async()=>{
 flt="ALL";
 render();
 await reloadPortal();
});
window.addEventListener("focus",reloadPortal);
document.addEventListener("visibilitychange",()=>{if(document.visibilityState==="visible")reloadPortal()});
setInterval(()=>{if(document.visibilityState==="visible")reloadPortal()},10000);

/* email verification notice (only when the server has email enabled) */
if(s.emailVerification&&!s.user.emailVerified){const vb=$("#vb");vb.className="ok";vb.style.cssText="max-width:1100px;margin:16px auto 0;padding:0 20px";vb.innerHTML='Please verify your email address: we sent a link to '+esc(s.user.email)+'. <button type="button" class="btn line sm" id="rv">Resend email</button>';
 $("#rv").onclick=async()=>{const r=await Auth.api("/api/auth/resend-verification",{});vb.firstChild.textContent=r.ok?"Verification email sent to "+s.user.email+". ":(r.data.error||"Could not send the email. ");}}
init();render();
})();
