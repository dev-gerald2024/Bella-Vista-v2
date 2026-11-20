/* Bella Vista Oasis Spa - the booking system (contact.html).
   Dynamic Room Recommendation & Multi-Section Booking Experience.
   Sections A to G: Customer Info, Treatment Details, Room Recommendation,
   Appointment Schedule, Health Declaration, Summary, and Final Confirmation. */
async function initBooking(){
  await CONFIG.ready;
  const f=document.getElementById("form");if(!f)return;
  const gateBox=document.getElementById("bookgate"),st=document.getElementById("status"),
   dt=document.getElementById("date"),interest=document.getElementById("interest"),
   duration=document.getElementById("duration"),guests=document.getElementById("guests"),
   tm=document.getElementById("time"),hint=document.getElementById("slotHint"),
   btn=document.getElementById("send"),
   esc=s=>String(s||"").replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;"}[c]));

  const s=await Auth.me();
  if(!s.authenticated){
    f.hidden=true;gateBox.hidden=false;
    if(s.offline)gateBox.querySelector("p").textContent="The account service is not available right now. Please try again shortly.";
    return;
  }
  if(s.emailVerification&&!s.user.emailVerified){
    f.hidden=true;gateBox.hidden=false;
    gateBox.querySelector("h3").textContent="Please verify your email";
    gateBox.querySelector("p").textContent="We sent a verification link to "+s.user.email+". Verify your email address, then return here to reserve.";
    gateBox.querySelector(".hb").innerHTML='<a class="btn" href="account.html">Go to my account</a>';
    return;
  }

  gateBox.hidden=true;f.hidden=false;
  const whoEl=document.getElementById("who");
  if(whoEl) whoEl.textContent="Signed in as "+(s.user.fullName||"Guest")+" ("+s.user.email+")";

  const phoneField=document.getElementById("phone");
  if(phoneField) formatPhoneInput(phoneField);

  // Populate treatment dropdown
  interest.innerHTML='<option value="">-- Choose a Treatment --</option>'+
    CONFIG.services.map(x=>`<option value="${esc(x.name)}">${esc(x.name)}</option>`).join("");

  // Track room state
  let currentRecData = null;
  let activeSelectedRoom = null; // object { id, roomCode, name, ... }

  function updateDurationOptions(){
    const selName=interest.value;
    const svc=CONFIG.services.find(x=>x.name===selName);
    duration.innerHTML="";
    if(!svc||!svc.durations||!svc.durations.length){
      if(selName==="Other Spa Treatments"||selName==="Not sure yet"){
        duration.innerHTML='<option value="">Management configuration required</option>';
        if(hint) hint.textContent="This service requires management duration confirmation before booking. Please contact the spa directly.";
      }else{
        duration.innerHTML='<option value="">Choose a treatment first</option>';
      }
      if(tm) tm.innerHTML='<option value="">Choose a configured treatment first</option>';
      updateLiveSummary();
      return;
    }
    duration.innerHTML=svc.durations.map(d=>{
      const pr=(svc.prices&&svc.prices[d])?" — ₱"+Number(svc.prices[d]).toLocaleString():"";
      return `<option value="${d}">${d} minutes session${pr}</option>`;
    }).join("");
    updateLiveSummary();
  }

  function updateLiveSummary(){
    const sumSvc=document.getElementById("sumService");
    const sumDur=document.getElementById("sumDuration");
    const sumGuests=document.getElementById("sumGuests");
    const sumRoom=document.getElementById("sumRoom");
    const sumSched=document.getElementById("sumSchedule");
    const disp=document.getElementById("priceDisplay");
    const bd=document.getElementById("priceBreakdown");

    const selName=interest.value;
    const durVal=duration.value;
    const gVal=parseInt(guests.value,10)||1;
    const dVal=dt.value;
    const tVal=tm.value;

    if(sumSvc) sumSvc.textContent=selName||"—";
    if(sumDur) sumDur.textContent=durVal?(durVal+" minutes"):"—";
    if(sumGuests) sumGuests.textContent=gVal+(gVal===1?" Guest":" Guests");
    if(sumRoom){
      sumRoom.textContent=activeSelectedRoom?(activeSelectedRoom.name||activeSelectedRoom.roomCode):"Executive Room 1 (SR-1)";
    }
    if(sumSched){
      if(dVal&&tVal){
        try{
          const parts=dVal.split("-");
          const dObj=new Date(parts[0],parts[1]-1,parts[2]);
          const dStr=dObj.toLocaleDateString("en-US",{month:"short",day:"numeric",year:"numeric"});
          sumSched.textContent=dStr+" at "+f12(tVal);
        }catch(e){
          sumSched.textContent=dVal+" at "+f12(tVal);
        }
      }else if(dVal){
        try{
          const parts=dVal.split("-");
          const dObj=new Date(parts[0],parts[1]-1,parts[2]);
          const dStr=dObj.toLocaleDateString("en-US",{month:"short",day:"numeric",year:"numeric"});
          sumSched.textContent=dStr+" (select time slot)";
        }catch(e){
          sumSched.textContent=dVal+" (select time slot)";
        }
      }else{
        sumSched.textContent="Choose date & time";
      }
    }

    const svc=CONFIG.services.find(x=>x.name===selName);
    const dur=parseInt(durVal,10);
    if(svc&&dur&&svc.prices&&svc.prices[dur]){
      const unitPrice=svc.prices[dur];
      const total=unitPrice*gVal;
      if(disp) disp.textContent="₱"+total.toLocaleString()+".00";
      if(bd) bd.textContent=`${selName} (${dur} min) @ ₱${unitPrice.toLocaleString()} × ${gVal} ${gVal===1?'guest':'guests'}`;
    }else{
      if(disp) disp.textContent="₱0.00";
      if(bd) bd.textContent="Select a treatment and duration to view official rate.";
    }
  }

  let recSeq=0;
  async function updateRoomRecommendations(){
    const svc=interest.value;
    const dur=parseInt(duration.value,10)||60;
    const g=parseInt(guests.value,10)||1;
    const d=dt.value||"";
    const t=tm.value||"";

    const mySeq=++recSeq;
    let url="/api/rooms/recommendations?service="+encodeURIComponent(svc)+
            "&duration="+encodeURIComponent(dur)+
            "&guests="+encodeURIComponent(g);
    if(d) url+="&date="+encodeURIComponent(d);
    if(t) url+="&time="+encodeURIComponent(t);

    try{
      const r=await Auth.api(url);
      if(mySeq!==recSeq)return;
      if(r.ok&&r.data){
        currentRecData=r.data;
        renderRooms(r.data);
      }
    }catch(err){
      console.error("Room recommendation error:",err);
    }
    updateLiveSummary();
  }

  function renderRooms(data){
    const rec=data.recommended;
    if(!rec)return;

    const g=parseInt(guests.value,10)||1;
    // Keep active selection if it still fits the current guest count; otherwise re-default to recommended
    if(!activeSelectedRoom||activeSelectedRoom.minGuests>g||activeSelectedRoom.maxCapacity<g){
      activeSelectedRoom=rec;
    }else{
      const refreshed=(data.allRooms||[]).find(x=>x.id===activeSelectedRoom.id);
      if(refreshed) activeSelectedRoom=refreshed;
    }

    const selCodeEl=document.getElementById("selectedRoomCode");
    const selIdEl=document.getElementById("selectedRoomId");
    if(selCodeEl) selCodeEl.value=activeSelectedRoom.roomCode;
    if(selIdEl) selIdEl.value=activeSelectedRoom.id;

    // Render recommended box
    const recNameEl=document.getElementById("recRoomName");
    const recStatusEl=document.getElementById("recRoomStatus");
    const recDescEl=document.getElementById("recRoomDesc");
    const recCapEl=document.getElementById("recRoomCap");
    const recRateEl=document.getElementById("recRoomRate");
    const recReasonEl=document.getElementById("recRoomReason");
    const recBox=document.getElementById("roomRecBox");

    if(recNameEl) recNameEl.textContent=rec.name;
    if(recStatusEl){
      const stClass=rec.isAvailable?"available":(rec.availabilityStatus==="MAINTENANCE"?"maintenance":"occupied");
      const stLabel=rec.isAvailable?"Available":(rec.availabilityStatus==="MAINTENANCE"?"Maintenance":"Occupied");
      recStatusEl.className="room-status-pill "+stClass;
      recStatusEl.textContent=stLabel;
    }
    if(recDescEl) recDescEl.textContent=rec.description;
    if(recCapEl) recCapEl.textContent=rec.capacityText;
    if(recRateEl) recRateEl.textContent=rec.rateText;
    if(recReasonEl){
      let rText=rec.recommendReason;
      if(rec.conflictReason) rText+=" Note: "+rec.conflictReason;
      recReasonEl.textContent=rText;
    }

    // Recommendation card interaction / active styling
    if(recBox){
      const isRecSelected=(activeSelectedRoom.id===rec.id);
      recBox.style.outline=isRecSelected?"2px solid var(--forest)":"none";
      recBox.style.cursor="pointer";
      recBox.title=isRecSelected?"Currently selected recommended suite":"Click to select this recommended suite";
      recBox.onclick=()=>{
        activeSelectedRoom=rec;
        if(selCodeEl) selCodeEl.value=rec.roomCode;
        if(selIdEl) selIdEl.value=rec.id;
        renderRooms(currentRecData);
        updateLiveSummary();
      };
    }

    // Alternative eligible rooms
    const altSec=document.getElementById("altRoomsSection");
    const altList=document.getElementById("altRoomsList");
    if(altSec&&altList){
      const alts=data.alternatives||[];
      if(alts.length===0){
        altSec.style.display="none";
        altList.innerHTML="";
      }else{
        altSec.style.display="block";
        altList.innerHTML=alts.map(a=>{
          const isSel=(activeSelectedRoom.id===a.id);
          const stClass=a.isAvailable?"available":(a.availabilityStatus==="MAINTENANCE"?"maintenance":"occupied");
          const stLabel=a.isAvailable?"Available":(a.availabilityStatus==="MAINTENANCE"?"Maintenance":"Occupied");
          return `<div class="alt-room-card ${isSel?'selected':''}" data-id="${a.id}" data-code="${esc(a.roomCode)}" style="position:relative">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <strong style="color:var(--forest);font-size:.92rem">${esc(a.name)}</strong>
              <span class="room-status-pill ${stClass}" style="font-size:.68rem;padding:2px 8px">${stLabel}</span>
            </div>
            <p style="font-size:.8rem;color:var(--mut);line-height:1.4;margin:0 0 6px">${esc(a.description)}</p>
            <div style="font-size:.76rem;display:flex;justify-content:space-between;color:#555;padding-top:4px;border-top:1px dashed #dcd8c8">
              <span>Capacity: <b>${esc(a.capacityText)}</b></span>
              <span>${esc(a.rateText)}</span>
            </div>
            ${isSel?'<div style="margin-top:6px;font-size:.76rem;color:var(--forest);font-weight:600">✓ Selected Alternative Suite</div>':'<div style="margin-top:6px;font-size:.74rem;color:#7a6b52">Click to select suite</div>'}
          </div>`;
        }).join("");

        altList.querySelectorAll(".alt-room-card").forEach(card=>{
          card.onclick=()=>{
            const rId=parseInt(card.getAttribute("data-id"),10);
            const found=alts.find(x=>x.id===rId);
            if(found){
              activeSelectedRoom=found;
              if(selCodeEl) selCodeEl.value=found.roomCode;
              if(selIdEl) selIdEl.value=found.id;
              renderRooms(currentRecData);
              updateLiveSummary();
            }
          };
        });
      }
    }
  }

  const pre=new URLSearchParams(location.search).get("service");
  if(pre&&CONFIG.services.some(x=>x.name===pre)){
    interest.value=pre;
  }
  updateDurationOptions();
  updateRoomRecommendations();

  const L=new Date();
  dt.min=L.getFullYear()+"-"+String(L.getMonth()+1).padStart(2,"0")+"-"+String(L.getDate()).padStart(2,"0");

  const f12=t=>{
    if(!t)return"";
    const[h,m]=t.split(":").map(Number);
    return(h%12||12)+":"+String(m).padStart(2,"0")+" "+(h<12?"AM":"PM");
  };

  let seq=0;
  async function loadSlots(keep){
    const d=dt.value;
    const svc=interest.value;
    const dur=duration.value;
    if(hint) hint.textContent="";

    if(!d||d<dt.min){
      tm.innerHTML='<option value="">Choose a date first</option>';
      return;
    }
    if(!svc){
      tm.innerHTML='<option value="">Choose a treatment first</option>';
      return;
    }
    if(!dur){
      tm.innerHTML='<option value="">Treatment requires duration configuration</option>';
      if(hint) hint.textContent="This treatment requires management configuration before booking.";
      return;
    }

    const n=++seq;
    tm.innerHTML='<option value="">Loading available times...</option>';
    const url="/api/availability?date="+encodeURIComponent(d)+
      "&service="+encodeURIComponent(svc)+
      "&duration="+encodeURIComponent(dur);
    const r=await Auth.api(url);
    if(n!==seq)return;

    if(!r.ok){
      tm.innerHTML='<option value="">Times unavailable</option>';
      if(hint) hint.textContent=(r.data&&r.data.error)||"Could not load available times. Please try again.";
      return;
    }
    const a=r.data;
    if(!a.open){
      tm.innerHTML='<option value="">Closed on this date</option>';
      if(hint) hint.textContent="The spa is closed on this date. Please choose another day.";
      return;
    }
    const free=a.slots.filter(x=>x.free);
    tm.innerHTML='<option value="">'+(free.length?"Choose an appointment time":"Fully booked on this date")+"</option>"+
      a.slots.map(x=>`<option value="${x.time}"${x.free?"":" disabled"}>${f12(x.time)}${x.free?"":" (fully booked / sanitizing)"}</option>`).join("");
    if(hint) hint.textContent="Operating hours: "+f12(a.opens)+" to "+f12(a.closes)+
      ". Treatment: "+a.minutes+" min (+ 30-min sanitization). Both treatment & cleaning finish by "+f12(a.closes)+".";
    if(keep&&free.some(x=>x.time===keep))tm.value=keep;
  }

  dt.addEventListener("change",()=>{loadSlots();updateRoomRecommendations();updateLiveSummary();});
  tm.addEventListener("change",()=>{updateRoomRecommendations();updateLiveSummary();});
  interest.addEventListener("change",()=>{updateDurationOptions();updateRoomRecommendations();loadSlots();updateLiveSummary();});
  duration.addEventListener("change",()=>{updateRoomRecommendations();loadSlots();updateLiveSummary();});

  guests.addEventListener("input",()=>{updateRoomRecommendations();updateLiveSummary();});
  guests.addEventListener("change",()=>{updateRoomRecommendations();updateLiveSummary();});

  // Clear errors on change
  const agreeHealthBox=document.getElementById("agreeHealth");
  const agreeTermsBox=document.getElementById("agreeTerms");
  if(agreeHealthBox) agreeHealthBox.addEventListener("change",()=>{
    const wrap=agreeHealthBox.closest(".f");
    if(wrap&&agreeHealthBox.checked) wrap.classList.remove("bad");
  });
  if(agreeTermsBox) agreeTermsBox.addEventListener("change",()=>{
    const wrap=agreeTermsBox.closest(".f");
    if(wrap&&agreeTermsBox.checked) wrap.classList.remove("bad");
  });

  let sending=false;
  f.addEventListener("submit",async e=>{
    e.preventDefault();
    if(sending)return;

    let ok=true,firstBad=null;
    const flag=(el,isBad,msg)=>{
      if(!el)return;
      const wrap=el.closest(".f");
      if(wrap){
        wrap.classList.toggle("bad",isBad);
        if(msg&&isBad){const errEl=wrap.querySelector(".err");if(errEl)errEl.textContent=msg;}
      }
      if(isBad&&!firstBad)firstBad=el;
      if(isBad)ok=false;
    };

    const phoneVal=f.phone.value.trim();
    flag(f.phone,!isValidPhone(phoneVal),"Phone number must contain 11 digits (09…) or 12 digits (639…).");

    const svcVal=interest.value;
    flag(interest,!svcVal,"Please select a treatment.");

    const durVal=duration.value;
    flag(duration,!durVal||isNaN(durVal),"Please choose a valid treatment duration.");

    const dateVal=dt.value;
    flag(dt,!dateVal||dateVal<dt.min,"Please select a valid date (from today onward).");

    const guestVal=parseInt(guests.value,10);
    flag(guests,isNaN(guestVal)||guestVal<1||guestVal>6,"Guest count must be between 1 and 6.");

    const timeVal=tm.value;
    flag(tm,!timeVal,"Please select an available appointment time.");

    flag(agreeHealthBox,!agreeHealthBox||!agreeHealthBox.checked,"Please confirm your health declaration before submitting.");
    flag(agreeTermsBox,!agreeTermsBox||!agreeTermsBox.checked,"You must review and accept the Terms and Conditions to proceed.");

    if(!ok){
      if(firstBad)firstBad.focus();
      return;
    }

    const svc = CONFIG.services.find(x => x.name === svcVal);
    const durNum = parseInt(durVal, 10) || 60;
    const unitPrice = (svc && svc.prices && svc.prices[durNum]) ? svc.prices[durNum] : 0;
    const totalPrice = unitPrice * guestVal;
    const dpAmount = Math.round(totalPrice * 0.25);
    const custName = (s && s.user && s.user.fullName) ? s.user.fullName : "Guest";
    const custEmail = (s && s.user && s.user.email) ? s.user.email : "—";
    const custPhone = formatPhone(phoneVal) || phoneVal;
    const yr = new Date().getFullYear();
    const formattedSched = dateVal + " at " + (typeof f12 === "function" ? f12(timeVal) : timeVal);

    if(window.Notice && Notice.confirm){
      const recName = document.getElementById("recRoomName") ? document.getElementById("recRoomName").textContent : "Spa Suite";
      const confirmBook = await Notice.confirm({
        title: "Confirm Appointment Submission",
        message: "Are you ready to submit your appointment request for " + esc(svcVal) + "?",
        details: [
          ["Booking Reference", "Pending Confirmation (BK-" + yr + "-XXXX)"],
          ["Customer Name", custName],
          ["Email", custEmail],
          ["Phone", custPhone],
          ["Service", svcVal],
          ["Duration", durVal + " mins"],
          ["Guests", guestVal + (guestVal > 1 ? " Guests" : " Guest")],
          ["Suite", recName],
          ["Date & Time", formattedSched],
          ["Price", "₱" + totalPrice.toLocaleString() + ".00"],
          ["Downpayment", "₱" + dpAmount.toLocaleString() + ".00 (25% demo downpayment)"]
        ],
        confirmLabel: "Submit Appointment",
        cancelLabel: "Review Details"
      });
      if(!confirmBook) return;
    }

    sending=true;
    btn.disabled=true;
    btn.textContent="Submitting request...";
    st.className="status";

    const selRoomCode=document.getElementById("selectedRoomCode")?document.getElementById("selectedRoomCode").value:"";
    const selRoomId=document.getElementById("selectedRoomId")?document.getElementById("selectedRoomId").value:"";

    const payload={
      service:svcVal,
      duration:durVal,
      guests:guestVal,
      date:dateVal,
      time:timeVal,
      phone:formatPhone(phoneVal),
      message:f.msg?f.msg.value.trim():"",
      roomCode:selRoomCode,
      roomId:selRoomId,
      selectedRoomCode:selRoomCode,
      selectedRoomId:selRoomId,
      agreeHealth:!!(agreeHealthBox&&agreeHealthBox.checked),
      agreeTerms:!!(agreeTermsBox&&agreeTermsBox.checked)
    };

    const r=await Auth.api("/api/bookings",payload);
    sending=false;
    btn.disabled=false;
    btn.textContent="Request Appointment";

    if(r.ok){
      const assignedName=(activeSelectedRoom?activeSelectedRoom.name:"") || selRoomCode || "Assigned Suite";
      st.innerHTML=`<div style="background:#eef7ee;border:1px solid #c2e2c4;border-radius:6px;padding:18px;margin:16px 0;text-align:center">
        <div style="color:#205e26;font-size:1.2rem;font-weight:700;margin-bottom:8px">
          &#x2713; Appointment Requested: <b>${esc(r.data.booking.id)}</b>
        </div>
        <p style="margin:0 0 6px;color:#284e2c;font-size:.95rem">
          Your reservation request for <b>${esc(r.data.booking.service)}</b> on <b>${esc(r.data.booking.date)}</b> at <b>${esc(r.data.booking.time)}</b> has been submitted!
        </p>
        <p style="margin:0 0 14px;color:#35633a;font-size:.88rem">
          Assigned Suite: <b>${esc(assignedName)}</b> &bull; Guests: <b>${esc(r.data.booking.guests)}</b>
        </p>
        <a class="btn sm" href="account.html#bookings" style="display:inline-block;background:var(--forest,#203A2A);color:#fff;text-decoration:none;padding:10px 22px">
          View in My Bookings &rarr;
        </a>
      </div>`;
      st.className="status show";
      if(window.Notice && Notice.success){
        Notice.success("Appointment Requested — Receipt Details", "Your appointment request has been recorded. Here are your booking details:", {
          details: [
            ["Booking Reference", r.data.booking.id],
            ["Customer Name", custName],
            ["Email", custEmail],
            ["Phone", custPhone],
            ["Service", r.data.booking.service || svcVal],
            ["Duration", durVal + " mins"],
            ["Guests", guestVal + (guestVal > 1 ? " Guests" : " Guest")],
            ["Suite", assignedName],
            ["Date & Time", r.data.booking.date + " at " + (typeof f12 === "function" ? f12(r.data.booking.time) : r.data.booking.time)],
            ["Price", "₱" + totalPrice.toLocaleString() + ".00"],
            ["Downpayment", "₱" + dpAmount.toLocaleString() + ".00 (25% demo downpayment)"],
            ["Status", "PENDING (Unpaid)"]
          ]
        });
      }
      f.reset();
      activeSelectedRoom=null;
      updateDurationOptions();
      updateRoomRecommendations();
      loadSlots();
      return;
    }

    if(r.status===401){
      Auth.reset();f.hidden=true;gateBox.hidden=false;return;
    }

    const d=r.data||{};
    const fieldKey=d.field==="interest"?"interest":d.field;
    const fe=fieldKey&&f.elements[fieldKey];
    if(fe&&fe.closest(".f")){
      flag(fe,true,d.error||"Invalid entry.");
      fe.focus();
      if(r.status===409||d.field==="time"||d.field==="date")loadSlots();
    }else{
      st.textContent=d.error||"Something went wrong while processing your request.";
      st.className="status show";
      if(r.status===409)loadSlots();
    }
  });

  if(location.hash==="#booking")setTimeout(()=>document.getElementById("phone").focus({preventScroll:true}),600);
}
