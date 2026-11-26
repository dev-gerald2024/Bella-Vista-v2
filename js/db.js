/* Bella Vista Oasis Spa - client data layer for the customer portal (account.html) and the admin dashboard (admin.html).
   Reads are synchronous from a snapshot the server sent; every write goes to the server (which re-checks the session,
   ownership and admin role) and the snapshot is replaced by the server's answer. Nothing is stored in the browser. */
const DB=(()=>{
 let P=null,A=null;
 const W=async(url,body,method)=>{const r=await Auth.api(url,body===undefined?{}:body,method||"POST");if(r.ok){if(r.data.portal)P=r.data.portal;if(r.data.admin)A=r.data.admin}return r};
 const L=a=>a||[];
 return{
  async loadPortal(){const r=await Auth.api("/api/portal");if(r.ok&&r.data&&r.data.portal)P=r.data.portal;return r},
  async loadAdmin(){const r=await Auth.api("/api/admin/data");if(r.ok)A=r.data.admin;return r},
  /* ---- reads ---- */
  catalog:()=>(P&&P.catalog)||{},
  customer:()=>P&&P.customer,
  customers:()=>L(A&&A.customers),
  allBookings:()=>L(A&&A.bookings),
  archivedBookings:()=>L(A&&A.archived),
  checks:()=>L(A&&A.checks),live:()=>!!(A&&A.live),
  bookings:cid=>P?P.bookings:DB.allBookings().filter(b=>b.customerId===cid),
  allTxns:()=>L(A&&A.txns),
  txns:cid=>P?P.txns:DB.allTxns().filter(t=>t.customerId===cid),
  activity:cid=>P?P.activity:L(A&&A.activity).filter(x=>x.customerId===cid),
  notifs:()=>L(P&&P.notifs),
  favs:()=>L(P&&P.favs),
  content:k=>(A&&A.content&&A.content[k])||[],
  adminLog:()=>L(A&&A.adminLog),
  reschedules:()=>L(A&&A.reschedules),
  reviews:()=>L(A&&A.reviews),
  /* ---- Phase 5.0 reads ---- */
  rooms:()=>L(A&&A.rooms),
  roomCategories:()=>L(A&&A.roomCategories),
  packages:()=>L(A&&A.packages),
  therapists:()=>L(A&&A.therapists),
  cleanings:()=>L(A&&A.cleanings),
  roomAssignments:()=>L(A&&A.roomAssignments),
  therapistAssignments:()=>L(A&&A.therapistAssignments),
  roomStats:()=>(A&&A.roomStats)||{},
  /* ---- Phase 6.0 reads ---- */
  paymentAnalytics:()=>(A&&A.paymentAnalytics)||{},
  paymentSettings:()=>(A&&A.paymentSettings)||{},
  /* ---- customer writes ---- */
  updateProfile:p=>W("/api/portal/profile",p,"PATCH"),
  savePrefs:p=>W("/api/portal/preferences",p,"PUT"),
  cancelBooking:(id,reason)=>W("/api/portal/bookings/"+encodeURIComponent(id)+"/cancel",{reason}),
  markRead:()=>W("/api/portal/notifications/read"),
  toggleFav:key=>W("/api/portal/favorites",{key}),
  changePassword:async(current,next,confirm)=>{const r=await Auth.api("/api/portal/password",{current,next,confirm});if(r.ok){try{sessionStorage.removeItem("bvweak")}catch{}const b=document.getElementById("bvweak");if(b)b.remove()}return r},
  clearExtras:()=>W("/api/portal/clear"),
  hideBooking:id=>W("/api/portal/bookings/"+encodeURIComponent(id)+"/hide"),
  requestReschedule:(id,date,time,reason)=>W("/api/portal/bookings/"+encodeURIComponent(id)+"/reschedule",{date,time,reason}),
  withdrawReschedule:rid=>W("/api/portal/reschedules/"+encodeURIComponent(rid)+"/withdraw"),
  submitReview:(id,rating,comment)=>W("/api/portal/bookings/"+encodeURIComponent(id)+"/review",{rating,comment}),
  /* ---- Phase 6.0 customer writes ---- */
  getBookingPayment:async id=>await Auth.api("/api/portal/bookings/"+encodeURIComponent(id)+"/payment",null,"GET"),
  processDemoPayment:(id,payload)=>W("/api/portal/bookings/"+encodeURIComponent(id)+"/pay",payload),
  getReceipt:async txnId=>await Auth.api("/api/portal/transactions/"+encodeURIComponent(txnId)+"/receipt",null,"GET"),
  /* ---- admin writes ---- */
  decideReschedule:(id,decision,note)=>W("/api/admin/reschedules/"+encodeURIComponent(id)+"/decision",{decision,note}),
  setReviewHidden:(id,hidden)=>W("/api/admin/reviews/"+encodeURIComponent(id)+"/visibility",{hidden}),
  saveBooking:(d,id)=>W("/api/admin/bookings",{...d,id}),
  setStatus:(id,status,reason)=>W("/api/admin/bookings/"+encodeURIComponent(id)+"/status",{status,reason}),
  deleteBooking:id=>W("/api/admin/bookings/"+encodeURIComponent(id),{},"DELETE"),
  deleteBookingPermanent:id=>W("/api/admin/bookings/"+encodeURIComponent(id)+"?permanent=1",{},"DELETE"),
  restoreBooking:id=>W("/api/admin/bookings/"+encodeURIComponent(id)+"/restore"),
  setCustomerActive:(id,active)=>W("/api/admin/customers/"+encodeURIComponent(id)+"/active",{active}),
  saveContent:(k,items)=>W("/api/admin/content/"+k,{items},"PUT"),
  /* ---- Phase 5.0 admin writes ---- */
  saveRoom:(d,id)=>W("/api/admin/rooms",{...d,id}),
  deactivateRoom:id=>W("/api/admin/rooms/"+encodeURIComponent(id)+"/deactivate"),
  saveRoomMaintenance:(id,d)=>W("/api/admin/rooms/"+encodeURIComponent(id)+"/maintenance",d),
  savePackage:(d,id)=>W("/api/admin/packages",{...d,id}),
  saveTherapist:(d,id)=>W("/api/admin/therapists",{...d,id}),
  saveTherapistSchedule:(id,shifts)=>W("/api/admin/therapists/"+encodeURIComponent(id)+"/schedule",{shifts},"PUT"),
  addTherapistBreak:(id,d)=>W("/api/admin/therapists/"+encodeURIComponent(id)+"/breaks",d),
  addTherapistLeave:(id,d)=>W("/api/admin/therapists/"+encodeURIComponent(id)+"/leaves",d),
  assignRoom:(bkId,roomId,reason)=>W("/api/admin/bookings/"+encodeURIComponent(bkId)+"/assign-room",{roomId,reason:reason||""}),
  assignTherapist:(bkId,therapistId)=>W("/api/admin/bookings/"+encodeURIComponent(bkId)+"/assign-therapist",{therapistId}),
  setCleaningStatus:(id,status,cleanedBy,notes)=>W("/api/admin/cleanings/"+encodeURIComponent(id)+"/status",{status,cleanedBy,notes}),
  /* ---- Phase 6.0 admin writes ---- */
  getAdminReceipt:async txnId=>await Auth.api("/api/admin/transactions/"+encodeURIComponent(txnId)+"/receipt",null,"GET"),
  confirmCashPayment:txnId=>W("/api/admin/transactions/"+encodeURIComponent(txnId)+"/confirm-cash"),
  processRefund:(bkId,amount,reason)=>W("/api/admin/bookings/"+encodeURIComponent(bkId)+"/refund",{amount,reason}),
  forfeitDownpayment:(bkId,reason)=>W("/api/admin/bookings/"+encodeURIComponent(bkId)+"/forfeit",{reason}),
  uploadTeamPhoto:data=>W("/api/admin/team-photo",{data}),
  runReminders:()=>W("/api/admin/reminders/run",{}),
  savePaymentSetting:(key,value)=>W("/api/admin/payments/settings",{key,value},"PUT")
 }})();
