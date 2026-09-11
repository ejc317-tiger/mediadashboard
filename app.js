const companies = [
  {name:'Model N',initial:'M',sector:'Enterprise Software',owner:'Vista Equity Partners',event:'Acquisition · Sep 2026'},
  {name:'Harvey',initial:'H',sector:'AI Applications',owner:'Venture-backed',event:'Series E · Sep 2026'},
  {name:'Dataiku',initial:'D',sector:'AI / Data Platform',owner:'Private',event:'Financing · Aug 2026'},
  {name:'Precisely',initial:'P',sector:'Data Software',owner:'Clearlake / TA',event:'Add-on · Aug 2026'},
  {name:'Nscale',initial:'N',sector:'AI Infrastructure',owner:'Venture-backed',event:'Funding · Jul 2026'}
];
const spacs = [
  {name:'Quetta Acquisition Corp',sponsor:'Quetta Capital',size:'$230M',deadline:'2027-03-18',remaining:'6 months'},
  {name:'Archimedes Tech SPAC II',sponsor:'Archimedes Advisors',size:'$175M',deadline:'2027-05-09',remaining:'8 months'},
  {name:'Aurora Technology III',sponsor:'Aurora Partners',size:'$250M',deadline:'2027-07-24',remaining:'10 months'},
  {name:'Pioneer Capital II',sponsor:'Pioneer Group',size:'$300M',deadline:'2027-10-02',remaining:'13 months'}
];
const centers = [
  {name:'Vantage FRA2',region:'Europe',country:'Germany',owner:'Vantage',builder:'Vantage / PORR',power:'96 MW',tenant:'Undisclosed',financing:'€600M facility'},
  {name:'Ashburn VA-7',region:'North America',country:'United States',owner:'Digital Realty',builder:'DPR Construction',power:'72 MW',tenant:'Multi-tenant',financing:'Corporate facility'},
  {name:'SIN12 Campus',region:'Asia Pacific',country:'Singapore',owner:'Equinix',builder:'Gammon',power:'48 MW',tenant:'Multi-tenant',financing:'Green bond'},
  {name:'Hillsboro PDX4',region:'North America',country:'United States',owner:'QTS',builder:'Turner',power:'120 MW',tenant:'Hyperscale',financing:'Private credit'},
  {name:'Sydney SY9',region:'Asia Pacific',country:'Australia',owner:'AirTrunk',builder:'Multiplex',power:'92 MW',tenant:'Undisclosed',financing:'A$850M facility'}
];

const companyRows = document.querySelector('#companyRows');
companyRows.innerHTML = companies.slice(0,4).map(c=>`<tr><td class="company-cell"><span class="company-logo">${c.initial}</span>${c.name}</td><td>${c.sector}</td><td><span class="tag">${c.owner}</span></td><td>${c.event}</td></tr>`).join('');
document.querySelector('#spacRows').innerHTML = spacs.slice(0,3).map(s=>{const d=new Date(s.deadline+'T00:00:00');return `<div class="deadline"><div class="deadline-date">${d.toLocaleString('en',{month:'short'}).toUpperCase()}<b>${d.getDate()}</b></div><div><strong>${s.name}</strong><small>${s.sponsor} · ${s.size} IPO</small></div><span>${s.remaining}</span></div>`}).join('');

const views = {
  companies:{eyebrow:'OPPORTUNITY DATABASE',title:'Companies',subtitle:'Private equity-owned technology businesses and the broader AI landscape.',filter:['Enterprise Software','AI Applications','AI / Data Platform','Data Software','AI Infrastructure'],headers:['COMPANY','SECTOR','OWNERSHIP','LATEST EVENT'],data:companies,row:c=>[c.name,c.sector,c.owner,c.event]},
  datacenters:{eyebrow:'INFRASTRUCTURE INTELLIGENCE',title:'Data centers & capacity',subtitle:'Track ownership, power, tenants, builders, and facility financing worldwide.',filter:['North America','Europe','Asia Pacific'],headers:['FACILITY','REGION / COUNTRY','OWNER / BUILDER','POWER','TENANT','FINANCING'],data:centers,row:c=>[c.name,`${c.region} · ${c.country}`,`${c.owner} · ${c.builder}`,c.power,c.tenant,c.financing]},
  spacs:{eyebrow:'CAPITAL MARKETS',title:'Active SPACs',subtitle:'Monitor sponsors, original deal sizes, and time remaining to complete a transaction.',filter:spacs.map(s=>s.sponsor),headers:['SPAC','SPONSOR','IPO SIZE','DEADLINE','TIME REMAINING'],data:spacs,row:s=>[s.name,s.sponsor,s.size,new Date(s.deadline+'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}),s.remaining]}
};
let currentView='overview';
function openView(name){
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.toggle('active',n.dataset.view===name));
  document.querySelector('#pageCrumb').textContent=name==='overview'?'Overview':views[name].title;
  document.querySelector('#overviewView').classList.toggle('hidden',name!=='overview');
  document.querySelector('#moduleView').classList.toggle('hidden',name==='overview');
  currentView=name;
  if(name==='overview') return;
  const view=views[name];
  document.querySelector('#moduleEyebrow').textContent=view.eyebrow;document.querySelector('#moduleTitle').textContent=view.title;document.querySelector('#moduleSubtitle').textContent=view.subtitle;
  const filter=document.querySelector('#moduleFilter');filter.innerHTML='<option value="all">All categories</option>'+view.filter.map(v=>`<option>${v}</option>`).join('');
  document.querySelector('#moduleSearch').value='';renderModule();window.scrollTo(0,0);
}
function renderModule(){
  const v=views[currentView],q=document.querySelector('#moduleSearch').value.toLowerCase(),filter=document.querySelector('#moduleFilter').value;
  document.querySelector('#moduleHead').innerHTML=`<tr>${v.headers.map(h=>`<th>${h}</th>`).join('')}</tr>`;
  const records=v.data.filter(r=>{const vals=v.row(r);return vals.join(' ').toLowerCase().includes(q)&&(filter==='all'||vals.join(' ').includes(filter))});
  document.querySelector('#moduleBody').innerHTML=records.map(r=>`<tr>${v.row(r).map((x,i)=>`<td${i===0?' class="company-cell"':''}>${i===0?`<span class="company-logo">${x[0]}</span>`:''}${x}</td>`).join('')}</tr>`).join('');
  document.querySelector('#emptyState').classList.toggle('hidden',records.length>0);
}
document.addEventListener('click',e=>{const target=e.target.closest('[data-view]');if(target&&views[target.dataset.view]||target?.dataset.view==='overview')openView(target.dataset.view)});
document.querySelector('#moduleSearch').addEventListener('input',renderModule);document.querySelector('#moduleFilter').addEventListener('change',renderModule);

const dialog=document.querySelector('#aiDialog');document.querySelector('#askAi').onclick=()=>dialog.showModal();document.querySelector('.dialog-close').onclick=()=>dialog.close();
document.querySelector('#aiForm').addEventListener('submit',e=>{e.preventDefault();dialog.showModal();dialog.querySelector('textarea').value=document.querySelector('#aiInput').value;dialog.querySelector('textarea').focus()});
document.querySelector('#dialogForm').addEventListener('submit',e=>{e.preventDefault();dialog.close();showToast('Research started — we’ll notify you when it’s ready.')});
function showToast(message){const toast=document.querySelector('#toast');toast.textContent=message;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),3200)}
document.querySelectorAll('.suggestions button').forEach(b=>b.onclick=()=>{document.querySelector('#aiInput').value=b.textContent;document.querySelector('#aiInput').focus()});
