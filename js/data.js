/* Bella Vista Oasis Spa - shared data. Loaded on every page BEFORE booking.js and main.js. */
/* ===== EDIT HERE: all business content lives in CONFIG. Set `img` to a photo URL to replace any placeholder. ===== */
const CONFIG = {
  contact: {
    address: "123 Bella Vista Drive, Tagaytay City, Cavite 4120, Philippines",
    phone: "+63 917 123 4567",
    email: "hello@bellavistaoasis.example",
    hours: "Daily, 1:00 PM - 9:00 PM",
    facebook: "https://www.facebook.com/",
    instagram: "",
    mapQuery:
      "https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3865.6028796117225!2d120.90700007486741!3d14.334484986120897!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397d4d9ae4ccbc1%3A0x4b3949da355185a1!2sBella%20Vista!5e0!3m2!1sen!2sph!4v1795153490117!5m2!1sen!2sph",
  },
  hero: { img: "assets/images/Serene-Plumeria-Spa-Massage.png" },
  ambiance: { img: "assets/images/relax_2.png" },
  about: [
    { img: "assets/images/tuanfoto-woman-6144774_1920.jpg" },
    { img: "assets/images/Bella-Vista-Oasis-Spa-Serenity.png" },
    { img: "assets/images/about-3.png" },
  ],

  wellness: { img: "assets/images/tremaestro-massage-therapy-1584711.jpg" },
  amenities: [],
  stayInfo: "",
  faqExtra: [],
  wellnessItems: [],
  gallery: [] /* filled from the admin dashboard (see CONFIG.ready below) */,
  rooms: [
    {
      name: "Executive Room",
      desc: "Private single treatment room with en-suite shower, ambient lighting, and therapeutic massage bed.",
      img: "assets/images/executive-room.jpg",
    },
    {
      name: "Junior Suite",
      desc: "Couples deluxe suite featuring dual massage beds, aromatic steam shower, and relaxation lounge.",
      img: "assets/images/couple-lounge.jpg",
    },
    {
      name: "Premier Suite",
      desc: "Luxury VIP spa pavilion with hydrotherapy tub, private couples treatment area, and garden view.",
      img: "assets/images/single-spa.jpg",
    },
  ],
  services: [
    {
      name: "Body Massage",
      cat: "Massage",
      desc: "Restorative full-body Swedish and Shiatsu therapy relieving deep muscle tension and restoring vitality.",
      img: "assets/images/body-massage.jpg",
      minDuration: 60,
      maxDuration: 90,
      durations: [60, 90],
      prices: { 60: 800, 90: 1100 },
    },
    {
      name: "Foot Massage",
      cat: "Massage",
      desc: "Targeted reflexology and soothing botanical foot soak to alleviate soreness and stimulate circulation.",
      img: "assets/images/foot-massage.jpg",
      minDuration: 30,
      maxDuration: 45,
      durations: [30, 45],
      prices: { 30: 450, 45: 600 },
    },
    {
      name: "Body Scrub",
      cat: "Body rituals",
      desc: "Gentle mineral sea salt and botanical exfoliation that polishes skin to a soft, radiant glow.",
      img: "assets/images/body-scrub.jpg",
      minDuration: 45,
      maxDuration: 60,
      durations: [45, 60],
      prices: { 45: 800, 60: 1000 },
    },
    {
      name: "Facial Treatment",
      cat: "Facial & beauty",
      desc: "Deep pore cleansing, nourishing herbal steam, and hydrating serum infusion for glowing skin.",
      img: "assets/images/kimia-kazemi-RbIcMsh0NSk-unsplash.jpg",
      minDuration: 45,
      maxDuration: 60,
      durations: [45, 60],
      prices: { 45: 700, 60: 900 },
    },
    {
      name: "Hair and Scalp Treatment",
      cat: "Hair & scalp",
      desc: "Revitalizing warm herbal oil therapy and pressure-point scalp massage to ease stress and nourish roots.",
      img: "assets/images/hair-scalp.jpg",
      minDuration: 30,
      maxDuration: 45,
      durations: [30, 45],
      prices: { 30: 500, 45: 700 },
    },
    {
      name: "Other Spa Treatments",
      cat: "Wellness",
      desc: "Customized aromatherapy wraps, hydrothermal relaxation, and signature wellness rituals.",
      img: "assets/images/others.jpg",
    },
  ],
};

/* Everything saved in the admin dashboard is merged in before any page renders:
   Services / Accommodations: same name = replaces the text, new name = added.
   FAQs: same question = replaces the answer, new question = added. Wellness and Gallery: shown on their public sections.
   Content blocks: replace one named text (address, phone, email, hours, brand statement, stay info, amenities).
   If the server does not answer, the lists above are used as they are. */
CONFIG.ready = (async () => {
  try {
    const c = new AbortController(),
      t = setTimeout(() => c.abort(), 2000),
      r = await fetch("api/content", {
        credentials: "same-origin",
        signal: c.signal,
      });
    clearTimeout(t);
    if (!r.ok) return;
    const d = await r.json(),
      mix = (base, extra, room) => {
        (extra || []).forEach((x) => {
          if (!x || typeof x.name !== "string" || !x.name.trim()) return;
          const i = base.findIndex((o) => o.name === x.name);
          if (i >= 0) {
            base[i] = {
              ...base[i],
              desc: x.desc || base[i].desc,
              cat: x.cat || base[i].cat,
            };
          } else {
            const n = {
              name: x.name,
              desc: x.desc || "",
              cat: x.cat || "Wellness",
              img: "",
            };
            room
              ? base.splice(Math.max(0, base.length - 1), 0, n)
              : base.push(n);
          }
        });
      };
    mix(CONFIG.services, d.services, false);
    mix(CONFIG.rooms, d.rooms, true);
    CONFIG.faqExtra = (d.faqs || []).filter((x) => x && x.q && x.a);
    CONFIG.wellnessItems = (d.wellness || []).filter((x) => x && x.name);
    CONFIG.gallery = (d.gallery || []).filter(
      (x) => x && /^assets\//.test(x.path || ""),
    );
    const CT = {
        "Brand statement": (t) => {
          SITE.statement = t;
        },
        Address: (t) => {
          CONFIG.contact.address = t;
        },
        Phone: (t) => {
          CONFIG.contact.phone = t;
        },
        Email: (t) => {
          if (/^[^\s"'<>@]+@[^\s"'<>@]+\.[^\s"'<>@]+$/.test(t))
            CONFIG.contact.email = t;
        },
        "Opening hours": (t) => {
          CONFIG.contact.hours = t;
        },
        "Accommodation information": (t) => {
          CONFIG.stayInfo = t;
        },
        "Room amenities": (t) => {
          CONFIG.amenities = t
            .split(/\r?\n/)
            .map((s) => s.trim())
            .filter(Boolean);
        },
      },
      seen = {};
    (d.content || []).forEach((x) => {
      if (!x || seen[x.section] || !CT[x.section] || !x.text) return;
      seen[x.section] = 1;
      CT[x.section](x.text);
    });
  } catch {}
})();

/* Navigation: single source for header, mobile menu and footer. [label, href, page key] */
const SITE = {
  brand: "Bella Vista Oasis Spa",
  statement:
    "A peaceful sanctuary for holistic wellness and deep relaxation, welcoming guests since 2021.",
  bookHref: "contact.html#booking",
  nav: [
    ["Home", "index.html", "home"],
    ["About", "about.html", "about"],
    ["Services", "services.html", "services"],
    ["Rooms & Packages", "accommodations.html", "accommodations"],
    ["FAQs", "faqs.html", "faqs"],
    ["Contact", "contact.html", "contact"],
  ],
};

/* FAQ content, built only from CONFIG values (same questions as v4) */
function buildFAQ() {
  const C = CONFIG.contact,
    L = (a) => a.map((x) => x.name).join(", "),
    hasPh = C.phone.replace(/[^\d+]/g, "").length > 5;
  const base = [
    [
      "What treatments do you offer?",
      `Our services include ${L(CONFIG.services)}. Visit the Services page for details on each treatment.`,
    ],
    [
      "Do you offer accommodations?",
      `Yes. You can choose from ${L(CONFIG.rooms)}. Visit the Accommodations page to learn more.`,
    ],
    ["Where are you located?", C.address],
    ["What are your operating hours?", C.hours],
    [
      "How can I book an appointment?",
      `Send an inquiry with the appointment form on the Contact page${hasPh ? " or call " + C.phone : ""}, or email ${C.email}. The spa will reply to confirm availability.`,
    ],
  ];
  (CONFIG.faqExtra || []).forEach((x) => {
    const i = base.findIndex((b) => b[0] === x.q);
    if (i >= 0) base[i] = [x.q, x.a];
    else base.push([x.q, x.a]);
  });
  return base;
}

/* Philippine mobile number formatter (11 digits, 09XX XXX XXXX) */
function cleanPhone(s) {
  let d = String(s || "").replace(/\D/g, "");
  if (d.startsWith("639") && d.length === 12) d = "0" + d.slice(2);
  else if (d.startsWith("9") && d.length === 10) d = "0" + d;
  return d;
}
function isValidPhone(s) {
  const d = cleanPhone(s);
  return /^09\d{9}$/.test(d);
}
function formatPhone(s) {
  const d = cleanPhone(s);
  if (/^09\d{9}$/.test(d)) {
    return d.slice(0, 4) + " " + d.slice(4, 7) + " " + d.slice(7, 11);
  }
  return s;
}
function formatPhoneInput(el) {
  if (!el) return;
  el.setAttribute("maxlength", "13");
  el.setAttribute("placeholder", "09XX XXX XXXX");
  el.setAttribute("autocomplete", "tel");
  const applyMask = () => {
    let raw = el.value.replace(/\D/g, "");
    if (raw.length > 0 && !raw.startsWith("0")) {
      if (raw.startsWith("9")) raw = "0" + raw;
      else if (raw.startsWith("639")) raw = "0" + raw.slice(2);
      else raw = "09" + raw;
    } else if (raw.length >= 2 && !raw.startsWith("09")) {
      raw = "09" + raw.slice(2);
    }
    if (raw.length > 11) raw = raw.slice(0, 11);
    let fmt = "";
    if (raw.length <= 4) fmt = raw;
    else if (raw.length <= 7) fmt = raw.slice(0, 4) + " " + raw.slice(4);
    else fmt = raw.slice(0, 4) + " " + raw.slice(4, 7) + " " + raw.slice(7, 11);
    el.value = fmt;
  };
  el.addEventListener("input", applyMask);
  el.addEventListener("paste", () => setTimeout(applyMask, 10));
  if (el.value) applyMask();
}
