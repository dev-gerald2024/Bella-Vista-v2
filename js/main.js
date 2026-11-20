/* Bella Vista Oasis Spa - shared UI: header/footer, nav, renderers, dialogs, motion. Needs data.js + booking.js first. */
(async () => {
  await CONFIG.ready;
  const $ = (s) => document.querySelector(s),
    $$ = (s) => [...document.querySelectorAll(s)],
    C = CONFIG.contact,
    PAGE = document.body.dataset.page || "home";
  const esc = (s) =>
    s.replace(
      /[&<>"]/g,
      (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c],
    );
  const I = (p) =>
    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${p}</svg>`;
  const ic = {
    pin: I(
      '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.800 12 21 12 21z"/><circle cx="12" cy="9.500" r="2.500"/>',
    ),
    ph: I(
      '<path d="M5 4h4l2 5-2.500 1.500a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
    ),
    ml: I(
      '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    ),
    cl: I('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
    fb: I(
      '<path d="M15 3h-2.500A3.500 3.500 0 0 0 9 6.500V9H6v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h2z"/>',
    ),
    ig: I(
      '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.500 6.500h.01"/>',
    ),
  };
  const AR =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
  const tel = C.phone.replace(/[^\d+]/g, ""),
    hasPh = tel.length > 5;
  const link = (t, h) => (h ? `<a href="${h}">${esc(t)}</a>` : esc(t));

  /* ---------- shared shell: header, footer, floating CTA, dialog ---------- */
  const navLinks = SITE.nav
    .map(
      ([t, h, k]) =>
        `<li><a class="l${k === PAGE ? " on" : ""}" href="${h}"${k === PAGE ? ' aria-current="page"' : ""}>${t}</a></li>`,
    )
    .join("");
  document.body.insertAdjacentHTML(
    "afterbegin",
    `<a class="skip" href="#main">Skip to content</a>
<header><div class="wrap nav">
 <a class="logo" href="index.html" aria-label="${SITE.brand}, home"><img src="assets/logo/logo.png" width="56" height="56" alt="${SITE.brand}"><span class="wm">Bella Vista<br>Oasis Spa</span></a>
 <nav id="menu" aria-label="Main"><ul>${navLinks}</ul><a class="btn mob" href="${SITE.bookHref}">Book now &rarr;</a><a class="btn mob ghost" id="acctm" href="login.html">Login / Register</a></nav>
 <div class="navcta"><div class="acct" id="acct"><button class="al" id="acb" aria-expanded="false" aria-haspopup="true" aria-controls="acm">Account <span aria-hidden="true">&#9662;</span></button><div class="acm" id="acm" role="menu"><a href="login.html" role="menuitem">Login</a><a href="register.html" role="menuitem">Create Account</a></div></div><a class="btn sm book" href="${SITE.bookHref}">Book now</a>
  <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false" aria-controls="menu"><span></span><span></span><span></span></button></div>
</div></header><div class="prog" aria-hidden="true"></div>`,
  );
  const foot = SITE.nav
    .map(([t, h]) => `<li><a href="${h}">${t}</a></li>`)
    .join("");
  document.body.insertAdjacentHTML(
    "beforeend",
    `<footer><div class="bt f1" aria-hidden="true"></div><div class="wrap"><div class="fg">
 <div><a class="badge" href="index.html" aria-label="${SITE.brand}, home"><img src="assets/logo/logo.png" width="120" height="120" alt="${SITE.brand}" loading="lazy"></a><p style="max-width:34ch">${esc(SITE.statement)}</p>
  <div class="soc">${`<a href="${C.facebook}" target="_blank" rel="noopener" aria-label="Facebook">${ic.fb}</a>` + (C.instagram ? `<a href="${C.instagram}" target="_blank" rel="noopener" aria-label="Instagram">${ic.ig}</a>` : "")}</div></div>
 <div><h4>Navigate</h4><ul>${foot}</ul></div>
 <div><h4>Services</h4><ul>${CONFIG.services.map((s) => `<li><a href="services.html">${esc(s.name)}</a></li>`).join("")}</ul></div>
 <div><h4>Contact</h4><ul><li>${esc(C.address)}</li><li>${link(C.phone, hasPh && "tel:" + tel)}</li><li>${link(C.email, "mailto:" + C.email)}</li><li>${esc(C.hours)}</li></ul></div>
</div><div class="legal"><div>&copy; ${new Date().getFullYear()} ${SITE.brand}</div>
 <span><a href="#" data-doc="Privacy Policy">Privacy Policy</a><a href="#" data-doc="Terms and Conditions">Terms &amp; Conditions</a></span></div></div></footer>
<a class="fab" href="${SITE.bookHref}">Book now</a>
<dialog id="dlg" aria-labelledby="dt"><h3 id="dt"></h3><p id="db"></p><div class="hb"><a class="btn sm" id="dq" href="${SITE.bookHref}">Send an inquiry</a><button class="btn sm line" id="dx">Close</button></div></dialog>`,
  );

  /* ---------- renderers (same markup/classes as v4) ---------- */
  const ph = (o, cls = "") =>
    `<div class="ph ${cls} ${o.img ? "has" : ""}" ${o.img ? `style="background-image:url('${o.img}')"` : ""} role="img" aria-label="${esc(o.alt || o.name || "Spa photo")}"></div>`;
  const svcCard = (o, i) =>
    `<article class="item svc" data-cat="${esc(o.cat || "Wellness")}">${ph(o)}<div class="tx"><span class="cat">${esc(o.cat || "Wellness")}</span><h3>${esc(o.name)}</h3><p>${esc(o.desc)}</p></div><button class="go" data-k="s" data-i="${i}" aria-label="View service: ${esc(o.name)}"><span class="ai">${AR}</span></button></article>`;
  const roomCard = (o, i, big) =>
    `<article class="item ${big ? "rf" : "rs"}"><div class="rmi"><div class="zm">${ph(o)}</div></div><div class="rmt"><span class="eb">${/suite/i.test(o.name) ? "Suite" : "Room"}</span><h3>${esc(o.name)}</h3><p>${esc(o.desc)}</p><button class="ar" data-k="r" data-i="${i}" aria-label="Explore ${esc(o.name)}">Explore</button></div></article>`;
  const set = (sel, html) => {
    const el = $(sel);
    if (el) el.innerHTML = html;
  };
  {
    const R = CONFIG.rooms,
      f = R.length - 1;
    set(
      "[data-rooms]",
      roomCard(R[f], f, 1) +
        '<div class="rg">' +
        R.slice(0, f)
          .map((o, i) => roomCard(o, i))
          .join("") +
        "</div>",
    );
  }
  {
    const o = CONFIG.services[0];
    set(
      "[data-feat]",
      `<div class="rmi"><div class="zm">${ph(o)}</div></div><div class="rmt"><span class="eb">Featured ritual</span><h2>${esc(o.name)}</h2><p>${esc(o.desc)}</p><button class="ar" data-k="s" data-i="0" aria-label="Explore treatment: ${esc(o.name)}">Explore treatment</button></div>`,
    );
  }
  set(
    "[data-svc=home]",
    CONFIG.services
      .slice(1, 4)
      .map((o, i) => svcCard(o, i + 1))
      .join(""),
  );
  set("[data-svc=all]", CONFIG.services.map(svcCard).join(""));
  set("[data-about-photos]", CONFIG.about.map((o) => ph(o)).join(""));
  {
    const cats = [...new Set(CONFIG.services.map((s) => s.cat || "Wellness"))];
    set(
      "[data-cats]",
      `<button class="chip on" data-c="" aria-pressed="true">All</button>` +
        cats
          .map(
            (c) =>
              `<button class="chip" data-c="${esc(c)}" aria-pressed="false">${esc(c)}</button>`,
          )
          .join(""),
    );
    const cl = $("[data-cats]");
    if (cl)
      cl.addEventListener("click", (e) => {
        const b = e.target.closest(".chip");
        if (!b) return;
        $$(".chip").forEach((x) => {
          x.classList.toggle("on", x === b);
          x.setAttribute("aria-pressed", x === b);
        });
        $$("[data-svc=all] .svc").forEach(
          (c) => (c.hidden = !!b.dataset.c && c.dataset.cat !== b.dataset.c),
        );
      });
  }
  {
    const w = $("[data-wellness-photo]");
    if (w && CONFIG.wellness.img) {
      w.classList.add("has");
      w.style.backgroundImage = `url('${CONFIG.wellness.img}')`;
    }
  }
  set(
    "[data-amenities]",
    CONFIG.amenities && CONFIG.amenities.length
      ? CONFIG.amenities.map((a) => `<li>${esc(a)}</li>`).join("")
      : "<li>[Add room amenities here.]</li>",
  );
  set(
    "[data-stayinfo]",
    esc(
      CONFIG.stayInfo ||
        "[Add accommodation information here: check-in, policies, rates.]",
    ),
  );
  set(
    "#info",
    [
      [ic.pin, "Address", esc(C.address)],
      [ic.ph, "Phone", link(C.phone, hasPh && "tel:" + tel)],
      [ic.ml, "Email", link(C.email, "mailto:" + C.email)],
    ]
      .map((r) => `<li>${r[0]}<div><b>${r[1]}</b>${r[2]}</div></li>`)
      .join(""),
  );
  set("#hours", `<li>${ic.cl}<div><b>Hours</b>${esc(C.hours)}</div></li>`);
  set(
    "#map",
    C.mapQuery
      ? `<iframe title="Map to Bella Vista Oasis Spa" loading="lazy" src="${/^https?:\/\//i.test(C.mapQuery) ? esc(C.mapQuery) : `https://www.google.com/maps?q=${encodeURIComponent(C.mapQuery)}&output=embed`}"></iframe>`
      : `<iframe title="Map placeholder" srcdoc="<body style='margin:0;display:grid;place-items:center;height:100vh;font:14px sans-serif;color:#555;text-align:center'>Google Map appears here.<br>Set mapQuery in CONFIG.</body>"></iframe>`,
  );
  {
    const fb = $("#fb");
    if (fb) fb.href = C.facebook;
  }
  set(
    "#faqList",
    buildFAQ()
      .map(
        ([q, a], i) =>
          `<div class="fq${i ? "" : " open"}"><h3><button class="fqb" id="fq${i}" aria-expanded="${!i}" aria-controls="fp${i}">${esc(q)}<span class="pm" aria-hidden="true"></span></button></h3><div class="fqp" id="fp${i}" role="region" aria-labelledby="fq${i}"><div><p>${esc(a)}</p></div></div></div>`,
      )
      .join(""),
  );
  {
    const fl = $("#faqList");
    if (fl)
      fl.addEventListener("click", (e) => {
        const b = e.target.closest(".fqb");
        if (!b) return;
        b.setAttribute(
          "aria-expanded",
          b.closest(".fq").classList.toggle("open"),
        );
      });
  }

  /* wellness experiences + gallery (saved in the admin dashboard; the sections stay hidden until there is something to show) */
  {
    const w = CONFIG.wellnessItems || [],
      box = $("[data-wellness-items]");
    if (box) {
      box.hidden = !w.length;
      set(
        "[data-wellness-list]",
        w
          .map(
            (o) =>
              `<article class="wx"><span class="cat">${esc(o.cat || "Wellness")}</span><h3>${esc(o.name)}</h3><p>${esc(o.desc || "")}</p></article>`,
          )
          .join(""),
      );
    }
  }
  {
    const g = CONFIG.gallery || [];
    $$("[data-gallery]").forEach((box) => {
      const grid = box.querySelector(".gal");
      box.hidden = !g.length;
      if (!g.length) return;
      grid.innerHTML = g
        .map(
          (o) =>
            `<figure class="gi"><img src="${esc(o.path)}" alt="${esc(o.title || "Bella Vista Oasis Spa")}" loading="lazy">${o.title ? `<figcaption>${esc(o.title)}</figcaption>` : ""}</figure>`,
        )
        .join("");
      grid.addEventListener(
        "error",
        (e) => {
          const f = e.target.closest && e.target.closest(".gi");
          if (f) f.remove();
          if (!grid.children.length) box.hidden = true;
        },
        true,
      );
    });
  }

  /* hero / ambiance / page banner images (all from CONFIG) */
  if (!CONFIG.ambiance.img) CONFIG.ambiance.img = CONFIG.wellness.img;
  {
    const h = $(".hero .hbg");
    if (h && CONFIG.hero.img) {
      h.style.backgroundImage = `url('${CONFIG.hero.img}')`;
      $(".hero").classList.add("has");
    }
    const a = $(".ambg");
    if (a && CONFIG.ambiance.img) {
      a.style.backgroundImage = `url('${CONFIG.ambiance.img}')`;
      $(".amb").classList.add("has");
    }
    const u = CONFIG.ambiance.img || CONFIG.hero.img,
      b = $(".pb");
    if (b && u) {
      b.querySelector(".pbg").style.backgroundImage = `url('${u}')`;
      b.classList.add("has");
    }
  }
  /* botanical line art */
  {
    let p = '<path d="M50 312C46 230 54 130 62 12"/>';
    for (let i = 0; i < 9; i++) {
      const y = 34 + i * 28,
        x = 62 - (y - 12) * 0.04;
      p += `<ellipse cx="${x - 8}" cy="${y}" rx="4" ry="10" transform="rotate(-26 ${x - 8} ${y})"/><ellipse cx="${x + 8}" cy="${y - 8}" rx="4" ry="10" transform="rotate(26 ${x + 8} ${y - 8})"/>`;
    }
    const SV = `<svg viewBox="0 0 120 320" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" aria-hidden="true">${p}</svg>`;
    $$(".bt").forEach((e) => (e.innerHTML = SV));
  }

  /* ---------- nav ---------- */
  const b = $("#burger"),
    m = $("#menu"),
    hd = $("header");
  const tog = (o) => {
    m.classList.toggle("open", o);
    hd.classList.toggle("open", o);
    document.body.classList.toggle("lock", o);
    b.setAttribute("aria-expanded", o);
    b.setAttribute("aria-label", o ? "Close menu" : "Open menu");
  };
  b.onclick = () => tog(!m.classList.contains("open"));
  m.addEventListener("click", (e) => {
    if (e.target.closest("a")) tog(false);
  });
  document.addEventListener("keydown", (e) => {
    if (e.key == "Escape" && m.classList.contains("open")) {
      tog(false);
      b.focus();
    }
  });
  matchMedia("(min-width:961px)").addEventListener(
    "change",
    (e) => e.matches && tog(false),
  );
  const sc = () => hd.classList.toggle("sc", scrollY > 40);
  addEventListener("scroll", sc, { passive: true });
  sc();
  const acb = $("#acb"),
    acm = $("#acm");
  if (acb && acm) {
    acb.onclick = (e) => {
      e.stopPropagation();
      const o = !acm.classList.contains("open");
      acm.classList.toggle("open", o);
      acb.setAttribute("aria-expanded", o);
    };
    document.addEventListener("click", (e) => {
      if (!acm.contains(e.target) && e.target !== acb) {
        acm.classList.remove("open");
        acb.setAttribute("aria-expanded", "false");
      }
    });
  }

  /* ---------- dialogs ---------- */
  const d = $("#dlg");
  $("#dx").onclick = () => d.close();
  const open = (t, x, q = true) => {
    $("#dt").textContent = t;
    if (typeof x === "string" && x.trim().startsWith("<")) {
      $("#db").innerHTML = x;
    } else {
      $("#db").textContent = x;
    }
    $("#dq").hidden = !q;
    d.showModal();
  };
  $("#dq").addEventListener("click", () => {
    if (location.pathname.endsWith("contact.html")) d.close();
  });
  d.addEventListener("click", (e) => {
    if (e.target === d) d.close();
  });

  const DOCS = {
    "Terms and Conditions": `
  <div style="text-align:left;max-height:60vh;overflow-y:auto;padding-right:8px;font-size:0.88rem;line-height:1.55;color:#2c3a2e">
   <p style="margin-top:0"><strong>Bella Vista Oasis Spa — Official Terms &amp; Conditions (Documented v2.2)</strong></p>
   <h4 style="margin:12px 0 4px;color:var(--forest)">A. Arrival &amp; Cancellation Policies</h4>
   <p><strong>T&amp;C 01 – Arrival &amp; Grace Period:</strong> Clients must arrive at least 15 minutes before scheduled session. Late arrivals result in a shortened session time to keep subsequent bookings on schedule.</p>
   <p><strong>T&amp;C 02 – Cancellation Policy:</strong> Cancellations or rescheduling must be done at least 24 hours prior to appointment. Late cancellations forfeit any downpayment made.</p>
   <p><strong>T&amp;C 03 – Health Declaration:</strong> Clients are required to disclose any medical conditions, injuries, or pregnancy prior to selecting service.</p>
   <p><strong>T&amp;C 04 – Quiet Environment:</strong> Absolute silence and low volume must be maintained within the spa premises to preserve peaceful ambiance.</p>
   
   <h4 style="margin:12px 0 4px;color:var(--forest)">B. Booking &amp; Reservation Policies</h4>
   <p><strong>T&amp;C 05–08:</strong> All appointments are subject to room and therapist availability. Confirmation is sent via registered email. The spa reserves the right to review duplicate reservations.</p>

   <h4 style="margin:12px 0 4px;color:var(--forest)">C. Payment Simulation Policies</h4>
   <p><strong>T&amp;C 09–12:</strong> All payments processed through the website are strictly for demonstration and simulation purposes. The system does not collect actual money or process real financial transactions.</p>

   <h4 style="margin:12px 0 4px;color:var(--forest)">D. Locker &amp; Belongings</h4>
   <p><strong>T&amp;C 14 – Personal Belongings:</strong> Guests are encouraged to use the digital locker. A unique combination passcode is provided on the digital receipt. The passcode combination changes after every booking.</p>

   <h4 style="margin:12px 0 4px;color:var(--forest)">E. Health, Safety &amp; Consent</h4>
   <p><strong>T&amp;C 17–20:</strong> Truthful health information is required. The spa may postpone or modify treatments if unsafe. Clients provide informed consent before service starts.</p>

   <h4 style="margin:12px 0 4px;color:var(--forest)">F. Privacy &amp; Legal</h4>
   <p><strong>T&amp;C 21–24:</strong> Customer and medical information is confidential and handled in compliance with the Philippine Data Privacy Act of 2012.</p>
  </div>
 `,
    "Privacy Policy": `
  <div style="text-align:left;max-height:60vh;overflow-y:auto;padding-right:8px;font-size:0.88rem;line-height:1.55;color:#2c3a2e">
   <p style="margin-top:0"><strong>Privacy Notice — Compliance with the Philippine Data Privacy Act of 2012 (RA 10173)</strong></p>
   <p>Bella Vista Oasis Spa respects your fundamental right to privacy. This privacy notice explains how we collect, protect, and process personal data in connection with our wellness reservation platform.</p>
   <h4 style="margin:12px 0 4px;color:var(--forest)">1. Data We Collect</h4>
   <p>We collect your full name, email address, contact telephone number, appointment preferences, and health declaration disclosures required for treatment safety.</p>
   <h4 style="margin:12px 0 4px;color:var(--forest)">2. Purpose of Collection</h4>
   <p>Your information is collected solely to schedule and manage your spa appointments, assign qualified therapists, customize treatments for your health and safety, and communicate appointment updates.</p>
   <h4 style="margin:12px 0 4px;color:var(--forest)">3. Confidentiality &amp; Security</h4>
   <p>Health declarations and personal contact details are stored securely and accessed exclusively by authorized spa management and attending therapy staff.</p>
   <h4 style="margin:12px 0 4px;color:var(--forest)">4. Data Subject Rights</h4>
   <p>Under the Data Privacy Act of 2012, you have the right to access, rectify, or request removal of your personal information from our system at any time.</p>
  </div>
 `,
  };

  document.addEventListener("click", (e) => {
    const k = e.target.closest("[data-k]");
    if (k) {
      const o = (k.dataset.k == "r" ? CONFIG.rooms : CONFIG.services)[
        k.dataset.i
      ];
      open(
        o.name,
        o.desc +
          " Details and pricing have not been added yet. Send an inquiry to ask the spa.",
      );
    }
    const l = e.target.closest("[data-doc]");
    if (l) {
      e.preventDefault();
      const docName = l.dataset.doc;
      open(docName, DOCS[docName] || "[Document content unavailable.]", false);
    }
  });

  Auth.init();
  initBooking();
  Auth.page();

  /* ---------- motion system: tag -> observe once -> CSS animates ---------- */
  const RM = matchMedia("(prefers-reduced-motion:reduce)").matches,
    FINE = matchMedia("(hover:hover) and (pointer:fine)").matches;
  const tag = (sel, cls, step) =>
    $$(sel).forEach((el, i) => {
      el.classList.add("reveal");
      if (cls) el.classList.add(cls);
      if (step) el.style.setProperty("--d", i * step + "ms");
    });
  tag(".svc", "", 110);
  tag(".rf");
  tag(".rs", "", 130);
  tag(".ft .rmt", "reveal-right");
  tag(".tri>div", "", 110);
  tag(".jr li", "", 110);
  tag(".fg>div", "", 100);
  tag(".orb", "reveal-left");
  tag(".fq", "", 90);
  tag(".wx", "", 110);
  tag(".gi", "", 90);
  tag(".soc a", "", 90);
  tag(".badge");
  tag(".chips");
  tag(".panel", "", 110);
  $$(".col .ph,.rmi .ph").forEach((el, i) => {
    el.classList.add("reveal", "reveal-image");
    el.style.setProperty("--d", (i % 3) * 120 + "ms");
  });
  const ab = $(".abt");
  if (ab) ab.style.setProperty("--d", "350ms");
  const rvs = $$(".reveal,.pb,.bt,.auth"),
    fab = $(".fab"),
    vis = new Set();
  if ("IntersectionObserver" in window) {
    const ro = new IntersectionObserver(
      (es) =>
        es.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add("in");
            ro.unobserve(e.target);
          }
        }),
      { rootMargin: "0px 0px -8% 0px" },
    );
    rvs.forEach((el) => ro.observe(el));
    const fo = new IntersectionObserver((es) => {
      es.forEach((e) =>
        e.isIntersecting ? vis.add(e.target.id) : vis.delete(e.target.id),
      );
      fab.classList.toggle("off", vis.size > 0);
    });
    ["home", "booking"].forEach((i) => {
      const el = document.getElementById(i);
      if (el) fo.observe(el);
    });
  } else rvs.forEach((el) => el.classList.add("in"));
  /* scroll progress + very subtle parallax (max ~20px) + tiny desktop cursor motion */
  const pr = $(".prog"),
    hb = $(".hbg"),
    hero = $(".hero"),
    par = [$(".orb"), $(".ambg")];
  let mx = 0,
    my = 0,
    tk = 0;
  const fr = () => {
    tk = 0;
    const y = scrollY,
      t = document.documentElement.scrollHeight - innerHeight;
    pr.style.transform = `scaleX(${t > 0 ? y / t : 0})`;
    if (RM) return;
    if (hb && hero)
      hb.style.translate = `${mx}px ${Math.min(y * 0.04, 20) + my}px`;
    par.forEach((el) => {
      if (!el) return;
      const r = el.getBoundingClientRect(),
        dd = (r.top + r.height / 2 - innerHeight / 2) * -0.04;
      el.style.translate = `0 ${Math.max(-20, Math.min(20, dd))}px`;
    });
  };
  addEventListener("scroll", () => tk || (tk = requestAnimationFrame(fr)), {
    passive: true,
  });
  addEventListener("resize", fr);
  fr();
  if (FINE && !RM) {
    if (hero)
      hero.addEventListener("mousemove", (e) => {
        const r = hero.getBoundingClientRect();
        mx = ((e.clientX - r.left) / r.width - 0.5) * -8;
        my = ((e.clientY - r.top) / r.height - 0.5) * -6;
        tk || (tk = requestAnimationFrame(fr));
      });
    $$(".hero .btn,.cta .btn").forEach((el) => {
      el.addEventListener("mousemove", (e) => {
        const r = el.getBoundingClientRect(),
          c = (v, mm) => Math.max(-4, Math.min(4, (v - mm) * 0.1));
        el.style.translate = `${c(e.clientX, r.left + r.width / 2)}px ${c(e.clientY, r.top + r.height / 2)}px`;
      });
      el.addEventListener("mouseleave", () => (el.style.translate = ""));
    });
  }
})();
