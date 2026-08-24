/* BLOG INTERACTIONS */
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const progress=$("#progress"),backTop=$("#backTop"),menu=$("#menuBtn"),nav=$("#navLinks"),links=$$(".toc a"),sections=$$(".section");
function update(){const y=scrollY,h=document.documentElement.scrollHeight-innerHeight;progress.style.width=(h?y/h*100:0)+"%";backTop.classList.toggle("show",y>500);let cur="";sections.forEach(s=>{if(y>=s.offsetTop-150)cur=s.id});links.forEach(a=>a.classList.toggle("active",a.getAttribute("href")==="#"+cur))}
addEventListener("scroll",update,{passive:true});addEventListener("resize",update);backTop.onclick=()=>scrollTo({top:0,behavior:"smooth"});
menu.onclick=()=>{nav.classList.toggle("open");menu.textContent=nav.classList.contains("open")?"✕":"☰"};
$$(".nav-links a").forEach(a=>a.onclick=()=>{nav.classList.remove("open");menu.textContent="☰"});
$("#printBtn").onclick=()=>print();update();
