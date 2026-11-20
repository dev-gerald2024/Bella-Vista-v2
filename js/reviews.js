/* Guest reviews on the home page. Shows only reviews the spa has not hidden, with first name and last initial. Hidden entirely when there are none. */
(async()=>{
 const box=document.getElementById("reviews"),grid=document.getElementById("rvg");if(!box||!grid)return;
 const esc=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c])),st=n=>"\u2605".repeat(n)+"\u2606".repeat(5-n);
 try{
  const r=await Auth.api("/api/reviews");if(!r.ok||!r.data||!r.data.reviews||!r.data.reviews.length)return;
  const d=r.data,mo=x=>new Date(x+"T00:00").toLocaleDateString("en-US",{month:"long",year:"numeric"});
  document.getElementById("rvs").textContent=d.average.toFixed(1)+" out of 5 from "+d.count+(d.count===1?" review":" reviews")+".";
  grid.innerHTML=d.reviews.map(x=>`<figure class="rvc"><div class="rvs" role="img" aria-label="${x.rating} out of 5 stars">${st(x.rating)}</div>${x.comment?`<blockquote>${esc(x.comment)}</blockquote>`:""}<figcaption><b>${esc(x.name)}</b> \u00B7 ${esc(x.service)}<small>${mo(x.date)}</small></figcaption></figure>`).join("");
  box.hidden=false;
 }catch{}
})();
