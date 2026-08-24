const menuBtn = document.getElementById("menuBtn");
const navLinks = document.getElementById("navLinks");
menuBtn.addEventListener("click", () => navLinks.classList.toggle("open"));

document.querySelectorAll('a[href^="#"]').forEach(link => {
  link.addEventListener("click", () => navLinks.classList.remove("open"));
});

document.querySelectorAll("[data-product]").forEach(btn => {
  btn.addEventListener("click", () => {
    const product = btn.dataset.product;
    const select = document.getElementById("product");
    const match = [...select.options].find(o => o.textContent.startsWith(product));
    if (match) select.value = match.value;
  });
});

document.getElementById("demoForm").addEventListener("submit", () => {
  const name = document.getElementById("name").value.trim();
  const message = document.getElementById("formMessage");
  message.textContent = `Thanks ${name}! Sending your request...`;
});

const revealObserver=new IntersectionObserver((entries,observer)=>{entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add("visible");observer.unobserve(entry.target)}})},{threshold:.12});document.querySelectorAll(".reveal").forEach(el=>revealObserver.observe(el));

// PWA service worker + install prompt
if ("serviceWorker" in navigator) {
  window.addEventListener("load", () => {
    navigator.serviceWorker.register("./sw.js").catch(err => console.log("Service worker registration failed:", err));
  });
}

let deferredInstallPrompt = null;
const installBanner = document.getElementById("installBanner");
const installBtn = document.getElementById("installBtn");
const closeInstall = document.getElementById("closeInstall");

window.addEventListener("beforeinstallprompt", (event) => {
  event.preventDefault();
  deferredInstallPrompt = event;
  if (installBanner) installBanner.hidden = false;
});

if (installBtn) {
  installBtn.addEventListener("click", async () => {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    installBanner.hidden = true;
  });
}
if (closeInstall) closeInstall.addEventListener("click", () => installBanner.hidden = true);

window.addEventListener("appinstalled", () => {
  if (installBanner) installBanner.hidden = true;
});

// LOGANX product carousel
(() => {
  const track = document.getElementById("productCarousel");
  const dots = document.getElementById("carouselDots");
  if (!track || !dots) return;

  const slides = [...track.children];
  let index = 0;
  let timer;
  let startX = 0;

  slides.forEach((_, i) => {
    const dot = document.createElement("button");
    dot.className = "carousel-dot" + (i === 0 ? " active" : "");
    dot.type = "button";
    dot.setAttribute("aria-label", `Go to product ${i + 1}`);
    dot.onclick = () => go(i);
    dots.appendChild(dot);
  });

  function go(i) {
    index = (i + slides.length) % slides.length;
    track.style.transform = `translateX(-${index * 100}%)`;
    [...dots.children].forEach((d,n) => d.classList.toggle("active", n === index));
    reset();
  }

  document.querySelector(".carousel-btn.next")?.addEventListener("click", () => go(index + 1));
  document.querySelector(".carousel-btn.prev")?.addEventListener("click", () => go(index - 1));

  function reset() {
    clearInterval(timer);
    timer = setInterval(() => go(index + 1), 5000);
  }

  track.addEventListener("touchstart", e => startX = e.touches[0].clientX, {passive:true});
  track.addEventListener("touchend", e => {
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 45) go(index + (dx < 0 ? 1 : -1));
  }, {passive:true});

  track.addEventListener("mouseenter", () => clearInterval(timer));
  track.addEventListener("mouseleave", reset);
  reset();
})();
