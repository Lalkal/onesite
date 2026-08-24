/* MAIL SERVICE: opens the visitor's email client with a ready-made enquiry.
   For true server-side sending, connect this form to a backend/SMTP provider later. */
document.addEventListener("DOMContentLoaded",()=>{
  const f=document.getElementById("mailForm");
  if(!f)return;
  f.addEventListener("submit",e=>{
    e.preventDefault();
    const d=new FormData(f),to="vikneshb@zoho.com";
    const subject=`LOGANX Website - New Demo Request - ${d.get("product")}`;
    const body=`Name: ${d.get("name")}\nPhone/WhatsApp: ${d.get("phone")}\nProduct: ${d.get("product")}\n\nMessage:\n${d.get("message")}`;
    location.href=`mailto:${to}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  });
});
