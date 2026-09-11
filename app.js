const datasets = {
  companies: [
    {name:'Model N',category:'Enterprise Software',ownership:'Vista Equity Partners',status:'Private',metric:'$1.25B take-private',date:'2024-06-27',description:'Revenue optimization and compliance software for life sciences and high-tech companies.',source:'Company announcement',sourceUrl:'https://www.modeln.com/company/news/media-center/vista-equity-partners-completes-acquisition-of-model-n/',confidence:'Verified'},
    {name:'Cloudera',category:'Data & Analytics',ownership:'CD&R / KKR',status:'PE-backed',metric:'$5.3B take-private',date:'2021-10-08',description:'Enterprise data cloud platform spanning data engineering, analytics, and machine learning.',source:'Company announcement',sourceUrl:'https://www.cloudera.com/about/news-and-blogs/press-releases/2021-10-08-cloudera-completes-agreement-to-be-acquired-by-cd-r-and-kkr.html',confidence:'Verified'},
    {name:'Proofpoint',category:'Cybersecurity',ownership:'Thoma Bravo',status:'PE-backed',metric:'$12.3B take-private',date:'2021-08-31',description:'Human-centric cybersecurity and compliance software for enterprises.',source:'Company announcement',sourceUrl:'https://www.proofpoint.com/us/newsroom/press-releases/proofpoint-announces-closing-acquisition-thoma-bravo',confidence:'Verified'},
    {name:'Harvey',category:'AI Applications',ownership:'Venture-backed',status:'Private',metric:'Valuation requires refresh',date:'2026-09-11',description:'Domain-specific generative AI platform for legal and professional services workflows.',source:'Workspace research',sourceUrl:'#',confidence:'Review'},
    {name:'Dataiku',category:'AI Platforms',ownership:'Venture-backed',status:'Private',metric:'Valuation requires refresh',date:'2026-09-11',description:'Collaborative enterprise platform for analytics, machine learning, and generative AI.',source:'Workspace research',sourceUrl:'#',confidence:'Review'},
    {name:'CoreWeave',category:'AI Compute',ownership:'Public',status:'Public',metric:'Public market',date:'2026-09-11',description:'Cloud infrastructure optimized for accelerated computing and AI workloads.',source:'Company filings',sourceUrl:'https://investors.coreweave.com/',confidence:'Refresh'}
  ],
  datacenters: [
    {name:'Vantage FRA1',category:'Europe',location:'Frankfurt, Germany',owner:'Vantage Data Centers',builder:'Not disclosed',power:'55 MW',tenant:'Multi-tenant',financing:'Corporate / project financing',status:'Operating',date:'2026-09-11',description:'Multi-building Frankfurt campus record. Capacity and financing should be refreshed from current company disclosures.',source:'Company campus page',sourceUrl:'https://vantage-dc.com/data-center-locations/emea/frankfurt-germany/',confidence:'Refresh'},
    {name:'Equinix SG5',category:'Asia Pacific',location:'Singapore',owner:'Equinix',builder:'Not disclosed',power:'Data not normalized',tenant:'Multi-tenant',financing:'Corporate financing',status:'Operating',date:'2026-09-11',description:'International Business Exchange facility serving Singapore connectivity and colocation demand.',source:'Company facility page',sourceUrl:'https://www.equinix.com/data-centers/asia-pacific-colocation/singapore-colocation',confidence:'Refresh'},
    {name:'Digital Realty DUB Campus',category:'Europe',location:'Dublin, Ireland',owner:'Digital Realty',builder:'Not disclosed',power:'Data not normalized',tenant:'Multi-tenant',financing:'Corporate financing',status:'Operating',date:'2026-09-11',description:'Dublin metro campus within Digital Realty’s global colocation portfolio.',source:'Company market page',sourceUrl:'https://www.digitalrealty.com/data-centers/emea/dublin',confidence:'Refresh'},
    {name:'QTS Hillsboro',category:'North America',location:'Oregon, United States',owner:'QTS / Blackstone',builder:'Not disclosed',power:'Data not normalized',tenant:'Hyperscale / enterprise',financing:'Sponsor-backed platform',status:'Operating',date:'2026-09-11',description:'Large-scale data center campus in the Pacific Northwest.',source:'Company location page',sourceUrl:'https://qtsdatacenters.com/data-centers/hillsboro',confidence:'Refresh'},
    {name:'AirTrunk SYD1',category:'Asia Pacific',location:'Sydney, Australia',owner:'AirTrunk',builder:'Not disclosed',power:'130+ MW campus',tenant:'Hyperscale',financing:'Platform financing',status:'Operating',date:'2026-09-11',description:'Hyperscale data center campus serving cloud and large technology customers.',source:'Company location page',sourceUrl:'https://airtrunk.com/data-centres/sydney/',confidence:'Refresh'}
  ],
  spacs: [
    {name:'SPAC monitoring template',category:'Research queue',sponsor:'Pending SEC ingestion',size:'—',deadline:'—',remaining:'—',status:'Needs source',date:'2026-09-11',description:'Production SPAC records should ingest S-1, 10-Q, 8-K and extension proxy filings from EDGAR; deadlines must incorporate approved extensions and charter terms.',source:'SEC EDGAR',sourceUrl:'https://www.sec.gov/edgar/search/',confidence:'Needs ingestion'},
    {name:'Extension vote workflow',category:'Methodology',sponsor:'N/A',size:'—',deadline:'Dynamic',remaining:'Calculated',status:'Methodology',date:'2026-09-11',description:'Track original completion window, shareholder-approved extensions, monthly contributions to trust, redemption levels, and liquidation date.',source:'SEC investor bulletin',sourceUrl:'https://www.sec.gov/resources-for-investors/investor-alerts-bulletins/what-you-need-know-about-spacs-investor-bulletin',confidence:'Methodology'}
  ]
};
const viewConfig = {
  companies:{eyebrow:'PRIVATE MARKETS & AI',title:'Company intelligence',subtitle:'Track ownership, sector, transaction history, valuations, and business profiles.',filterLabel:'All sectors',headers:[['name','Company'],['category','Sector'],['ownership','Ownership'],['status','Status'],['metric','Latest disclosed metric'],['date','As of']]},
  datacenters:{eyebrow:'DIGITAL INFRASTRUCTURE',title:'Data centers & capacity',subtitle:'Track facilities by geography, owner, builder, power, tenant, financing, and operating status.',filterLabel:'All regions',headers:[['name','Facility'],['location','Location'],['owner','Owner'],['power','Power'],['tenant','Tenant'],['status','Status'],['date','As of']]},
  spacs:{eyebrow:'CAPITAL MARKETS',title:'Active SPAC monitor',subtitle:'A filing-led workflow for sponsors, IPO size, completion deadlines, extensions, and redemptions.',filterLabel:'All record types',headers:[['name','SPAC / workflow'],['sponsor','Sponsor'],['size','IPO size'],['deadline','Deadline'],['remaining','Time remaining'],['status','Status']]}
};
let currentView='overview', sortKey='name', sortDirection=1;
const $=selector=>document.querySelector(selector);
function safe(value){return String(value??'—').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function displayDate(value){if(!/^\d{4}-\d{2}-\d{2}$/.test(value||''))return value;return new Date(value+'T00:00:00Z').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric',timeZone:'UTC'});}
function openView(name){
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.toggle('active',n.dataset.view===name));
  $('#pageCrumb').textContent=name==='overview'?'Overview':viewConfig[name].title;
  $('#overviewView').classList.toggle('hidden',name!=='overview'); $('#moduleView').classList.toggle('hidden',name==='overview');
  currentView=name; if(name==='overview')return;
  const config=viewConfig[name]; $('#moduleEyebrow').textContent=config.eyebrow; $('#moduleTitle').textContent=config.title; $('#moduleSubtitle').textContent=config.subtitle;
  const categories=[...new Set(datasets[name].map(r=>r.category))]; $('#moduleFilter').innerHTML=`<option value="all">${config.filterLabel}</option>`+categories.map(s=>`<option>${safe(s)}</option>`).join('');
  $('#moduleSearch').value=''; sortKey='name'; sortDirection=1; renderModule(); window.scrollTo({top:0,behavior:'smooth'});
}
function filteredRecords(){
  const query=$('#moduleSearch').value.trim().toLowerCase(), filter=$('#moduleFilter').value;
  return datasets[currentView].filter(record=>Object.values(record).join(' ').toLowerCase().includes(query)&&(filter==='all'||record.category===filter)).sort((a,b)=>String(a[sortKey]??'').localeCompare(String(b[sortKey]??''),undefined,{numeric:true})*sortDirection);
}
function renderModule(){
  const config=viewConfig[currentView], records=filteredRecords();
  $('#moduleHead').innerHTML=`<tr>${config.headers.map(([key,label])=>`<th><button data-sort="${key}">${label}<span>${sortKey===key?(sortDirection===1?'↑':'↓'):'↕'}</span></button></th>`).join('')}</tr>`;
  $('#moduleBody').innerHTML=records.map(record=>`<tr data-record="${safe(record.name)}">${config.headers.map(([key],i)=>`<td class="${i===0?'company-cell ':''}${key==='status'?'status-cell':''}">${i===0?`<span class="company-logo">${safe(record.name[0])}</span>`:''}${key==='date'?displayDate(record[key]):safe(record[key])}</td>`).join('')}</tr>`).join('');
  $('#resultCount').textContent=`${records.length} ${records.length===1?'record':'records'}`; $('#emptyState').classList.toggle('hidden',records.length>0);
  const verified=datasets[currentView].filter(r=>r.confidence==='Verified').length;
  $('#moduleStats').innerHTML=`<article><span>Records</span><strong>${datasets[currentView].length}</strong></article><article><span>Verified</span><strong>${verified}</strong></article><article><span>Needs review</span><strong>${datasets[currentView].length-verified}</strong></article><article><span>Last workspace sync</span><strong>Sep 11, 2026</strong></article>`;
}
function openDrawer(record){
  $('#drawerType').textContent=viewConfig[currentView].eyebrow;
  $('#drawerContent').innerHTML=`<div class="drawer-title"><span class="company-logo large">${safe(record.name[0])}</span><div><h2>${safe(record.name)}</h2><p>${safe(record.category)}</p></div></div><p class="drawer-description">${safe(record.description)}</p><div class="research-grid">${Object.entries(record).filter(([k])=>!['name','category','description','sourceUrl'].includes(k)).map(([k,v])=>`<div><span>${safe(k.replace(/([A-Z])/g,' $1'))}</span><strong>${k==='date'?displayDate(v):safe(v)}</strong></div>`).join('')}</div><div class="source-card"><span>PRIMARY SOURCE</span><strong>${safe(record.source)}</strong><p>Open the linked disclosure and confirm all time-sensitive fields before relying on this record.</p><a href="${safe(record.sourceUrl)}" target="_blank" rel="noreferrer">Open source ↗</a></div>`;
  $('#detailDrawer').classList.add('open'); $('#drawerBackdrop').classList.add('open'); $('#detailDrawer').setAttribute('aria-hidden','false');
}
function closeDrawer(){ $('#detailDrawer').classList.remove('open'); $('#drawerBackdrop').classList.remove('open'); $('#detailDrawer').setAttribute('aria-hidden','true'); }
document.addEventListener('click',event=>{
  const viewButton=event.target.closest('[data-view]'); if(viewButton){openView(viewButton.dataset.view);return;}
  const sortButton=event.target.closest('[data-sort]'); if(sortButton){const key=sortButton.dataset.sort;sortDirection=sortKey===key?-sortDirection:1;sortKey=key;renderModule();return;}
  const row=event.target.closest('[data-record]'); if(row)openDrawer(datasets[currentView].find(record=>record.name===row.dataset.record));
});
$('#moduleSearch').addEventListener('input',renderModule); $('#moduleFilter').addEventListener('change',renderModule); $('#closeDrawer').onclick=closeDrawer; $('#drawerBackdrop').onclick=closeDrawer;
$('#dismissNotice').onclick=event=>event.currentTarget.parentElement.remove();
$('#exportBtn').onclick=()=>{const config=viewConfig[currentView],rows=filteredRecords(),csv=[config.headers.map(([,h])=>h),...rows.map(r=>config.headers.map(([k])=>r[k]??''))].map(row=>row.map(v=>`"${String(v).replaceAll('"','""')}"`).join(',')).join('\n');const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download=`northstar-${currentView}.csv`;a.click();URL.revokeObjectURL(a.href);};
$('#addRecord').onclick=()=>showToast('Record intake form is ready for data-source integration.');
const dialog=$('#aiDialog'); $('#askAi').onclick=()=>dialog.showModal(); $('.dialog-close').onclick=()=>dialog.close();
$('#aiForm').addEventListener('submit',event=>{event.preventDefault();dialog.showModal();dialog.querySelector('textarea').value=$('#aiInput').value;dialog.querySelector('textarea').focus();});
$('#dialogForm').addEventListener('submit',event=>{event.preventDefault();dialog.close();showToast('Research queued with source verification enabled.');});
function showToast(message){const toast=$('#toast');toast.textContent=message;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),3200);}
document.querySelectorAll('.suggestions button').forEach(button=>button.onclick=()=>{$('#aiInput').value=button.textContent;$('#aiInput').focus();});
$('#companyRows').innerHTML=datasets.companies.slice(0,4).map(record=>`<tr><td class="company-cell"><span class="company-logo">${safe(record.name[0])}</span>${safe(record.name)}</td><td>${safe(record.category)}</td><td><span class="tag">${safe(record.ownership)}</span></td><td>${safe(record.metric)}</td></tr>`).join('');
$('#spacRows').innerHTML=`<div class="deadline"><div class="deadline-date">SEC<b>↗</b></div><div><strong>Filing-led deadline monitor</strong><small>S-1 · 10-Q · 8-K · extension proxies</small></div><span>Needs ingestion</span></div><div class="deadline"><div class="deadline-date">QA<b>✓</b></div><div><strong>Deadline methodology</strong><small>Extensions · trust contributions · redemptions</small></div><span>Documented</span></div>`;
