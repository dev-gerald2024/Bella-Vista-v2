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
