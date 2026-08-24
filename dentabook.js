const DB_NAME = "DentaBookDB";
const DB_VERSION = 1;
let db;

const stores = [
  ["appointments", {keyPath:"id", autoIncrement:true}],
  ["patients", {keyPath:"id", autoIncrement:true}],
  ["invoices", {keyPath:"id", autoIncrement:true}]
];

function openDB(){
  return new Promise((resolve,reject)=>{
    const req=indexedDB.open(DB_NAME,DB_VERSION);
    req.onupgradeneeded=e=>{
      const database=e.target.result;
      stores.forEach(([name,opts])=>{
        if(!database.objectStoreNames.contains(name)) database.createObjectStore(name,opts);
      });
    };
    req.onsuccess=e=>{db=e.target.result;resolve(db)};
    req.onerror=()=>reject(req.error);
  });
}
function store(name,mode="readonly"){return db.transaction(name,mode).objectStore(name)}
function getAll(name){return new Promise((res,rej)=>{const r=store(name).getAll();r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)})}
function add(name,data){return new Promise((res,rej)=>{const r=store(name,"readwrite").add(data);r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error)})}
function remove(name,id){return new Promise((res,rej)=>{const r=store(name,"readwrite").delete(id);r.onsuccess=()=>res();r.onerror=()=>rej(r.error)})}

const esc = s => String(s ?? "").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[m]));

function renderAppointments(){
  getAll("appointments").then(rows=>{
    rows.sort((a,b)=>(a.date+a.time).localeCompare(b.date+b.time));
    document.getElementById("appointmentRows").innerHTML=rows.map(r=>`
      <tr><td>${esc(r.date)}</td><td>${esc(r.time)}</td><td>${esc(r.patient)}</td><td>${esc(r.doctor)}</td>
      <td>${esc(r.reason)}</td><td><span class="db-status">${esc(r.status)}</span></td>
      <td><button class="delete" onclick="deleteRecord('appointments',${r.id})">Delete</button></td></tr>`).join("") || `<tr><td colspan="7">No appointments yet.</td></tr>`;
    document.getElementById("heroCount").textContent=rows.filter(r=>r.date===new Date().toISOString().slice(0,10)).length;
  });
}
function renderPatients(){
  getAll("patients").then(rows=>{
    document.getElementById("patientRows").innerHTML=rows.map(r=>`
      <tr><td>${esc(r.name)}</td><td>${esc(r.mobile)}</td><td>${esc(r.email)}</td><td>${esc(r.dob)}</td><td>${esc(r.notes)}</td>
      <td><button class="delete" onclick="deleteRecord('patients',${r.id})">Delete</button></td></tr>`).join("") || `<tr><td colspan="6">No patients yet.</td></tr>`;
  });
}
function renderInvoices(){
  getAll("invoices").then(rows=>{
    document.getElementById("invoiceRows").innerHTML=rows.map(r=>`
      <tr><td>${esc(r.number)}</td><td>${esc(r.patient)}</td><td>₹${Number(r.amount).toFixed(2)}</td><td>${esc(r.status)}</td><td>${esc(r.description)}</td>
      <td><button class="delete" onclick="deleteRecord('invoices',${r.id})">Delete</button></td></tr>`).join("") || `<tr><td colspan="6">No invoices yet.</td></tr>`;
  });
}
window.deleteRecord=async(name,id)=>{if(confirm("Delete this record?")){await remove(name,id);renderAll()}};

function renderAll(){renderAppointments();renderPatients();renderInvoices()}

document.querySelectorAll(".tab").forEach(btn=>btn.addEventListener("click",()=>{
  document.querySelectorAll(".tab,.panel").forEach(x=>x.classList.remove("active"));
  btn.classList.add("active"); document.getElementById(btn.dataset.tab).classList.add("active");
}));

document.getElementById("appointmentForm").addEventListener("submit",async e=>{
  e.preventDefault();
  await add("appointments",{patient:aPatient.value,mobile:aMobile.value,doctor:aDoctor.value,date:aDate.value,time:aTime.value,reason:aReason.value,status:"Booked"});
  e.target.reset();renderAppointments();alert("Appointment saved.");
});
document.getElementById("patientForm").addEventListener("submit",async e=>{
  e.preventDefault();
  await add("patients",{name:pName.value,mobile:pMobile.value,email:pEmail.value,dob:pDob.value,notes:pNotes.value});
  e.target.reset();renderPatients();alert("Patient saved.");
});
document.getElementById("invoiceForm").addEventListener("submit",async e=>{
  e.preventDefault();
  await add("invoices",{patient:iPatient.value,number:iNo.value,amount:iAmount.value,status:iStatus.value,description:iDesc.value});
  e.target.reset();renderInvoices();alert("Invoice saved.");
});

openDB().then(()=>renderAll()).catch(()=>{
  document.getElementById("dbStatus").textContent="Database unavailable";
  document.getElementById("dbStatus").style.background="#fff0f0";
  document.getElementById("dbStatus").style.color="#c44141";
});
