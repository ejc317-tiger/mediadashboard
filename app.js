const viewConfig = {
  companies:{eyebrow:'PRIVATE MARKETS & AI',title:'Company intelligence',subtitle:'Track ownership, sector, transaction history, valuations, and business profiles.',filterLabel:'All sectors',headers:[['name','Company'],['category','Sector'],['ownership','Ownership'],['status','Status'],['metric','Latest disclosed metric'],['date','As of']]},
  datacenters:{eyebrow:'DIGITAL INFRASTRUCTURE',title:'Data centers & capacity',subtitle:'Track facilities by geography, owner, builder, power, tenant, financing, and operating status.',filterLabel:'All regions',headers:[['name','Facility'],['location','Location'],['owner','Owner'],['power','Power'],['tenant','Tenant'],['status','Status'],['date','As of']]},
  spacs:{eyebrow:'CAPITAL MARKETS',title:'Active SPAC monitor',subtitle:'A filing-led workflow for sponsors, IPO size, completion deadlines, extensions, and redemptions.',filterLabel:'All record types',headers:[['name','SPAC / workflow'],['sponsor','Sponsor'],['size','IPO size'],['deadline','Deadline'],['remaining','Time remaining'],['status','Status']]}
};
let currentView='overview', sortKey='name', sortDirection=1, sortTimer, records=[];
const $=selector=>document.querySelector(selector);
const fieldAliases={as_of_date:'date',source_name:'source',source_url:'sourceUrl',ipo_size:'size'};
function normalize(record){return Object.fromEntries(Object.entries(record).map(([key,value])=>[fieldAliases[key]||key,value]));}
function safe(value){return String(value??'—').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function displayDate(value){if(!/^\d{4}-\d{2}-\d{2}$/.test(value||''))return value||'—';return new Date(value+'T00:00:00Z').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric',timeZone:'UTC'});}
async function request(resource, parameters={}){
  const query=new URLSearchParams({resource,...parameters});
  const response=await fetch(`api.php?${query}`);
  const payload=await response.json();
  if(!response.ok)throw new Error(payload.error||'Unable to load records.');
  return {...payload,records:payload.records.map(normalize)};
}
async function openView(name){
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.toggle('active',n.dataset.view===name));
  $('#pageCrumb').textContent=name==='overview'?'Overview':viewConfig[name].title;
  $('#overviewView').classList.toggle('hidden',name!=='overview'); $('#moduleView').classList.toggle('hidden',name==='overview');
  currentView=name;if(name==='overview')return;
  const config=viewConfig[name];$('#moduleEyebrow').textContent=config.eyebrow;$('#moduleTitle').textContent=config.title;$('#moduleSubtitle').textContent=config.subtitle;
  $('#moduleSearch').value='';sortKey='name';sortDirection=1;window.scrollTo({top:0,behavior:'smooth'});await loadModule();
}
async function loadModule(){
  const config=viewConfig[currentView];
  $('#moduleBody').innerHTML='<tr><td class="loading-row" colspan="8">Loading records…</td></tr>';
  try{
    const payload=await request(currentView,{search:$('#moduleSearch').value,category:$('#moduleFilter').value,sort:sortKey==='date'?'as_of_date':sortKey==='size'?'ipo_size':sortKey,direction:sortDirection===1?'asc':'desc'});
    records=payload.records;
    $('#moduleFilter').innerHTML=`<option value="all">${config.filterLabel}</option>`+payload.categories.map(category=>`<option ${category===$('#moduleFilter').value?'selected':''}>${safe(category)}</option>`).join('');
    renderModule(payload.stats);
  }catch(error){records=[];$('#moduleBody').innerHTML='';$('#emptyState').classList.remove('hidden');$('#emptyState').innerHTML=`<strong>Could not load data</strong><span>${safe(error.message)} Check the MySQL connection and run schema.sql.</span>`;}
}
function renderModule(stats){
  const config=viewConfig[currentView];
  $('#moduleHead').innerHTML=`<tr>${config.headers.map(([key,label])=>`<th><button data-sort="${key}">${label}<span>${sortKey===key?(sortDirection===1?'↑':'↓'):'↕'}</span></button></th>`).join('')}</tr>`;
  $('#moduleBody').innerHTML=records.map(record=>`<tr data-record="${record.id}">${config.headers.map(([key],i)=>`<td class="${i===0?'company-cell ':''}${key==='status'?'status-cell':''}">${i===0?`<span class="company-logo">${safe(record.name[0])}</span>`:''}${key==='date'?displayDate(record[key]):safe(record[key])}</td>`).join('')}</tr>`).join('');
  $('#resultCount').textContent=`${records.length} ${records.length===1?'record':'records'}`;$('#emptyState').classList.toggle('hidden',records.length>0);
  const total=Number(stats.total||0),verified=Number(stats.verified||0),sync=stats.last_sync?new Date(stats.last_sync.replace(' ','T')+'Z').toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric'}):'Never';
  $('#moduleStats').innerHTML=`<article><span>Records</span><strong>${total}</strong></article><article><span>Verified</span><strong>${verified}</strong></article><article><span>Needs review</span><strong>${total-verified}</strong></article><article><span>Last database update</span><strong>${sync}</strong></article>`;
}
function openDrawer(record){
  $('#drawerType').textContent=viewConfig[currentView].eyebrow;
  $('#drawerContent').innerHTML=`<div class="drawer-title"><span class="company-logo large">${safe(record.name[0])}</span><div><h2>${safe(record.name)}</h2><p>${safe(record.category)}</p></div></div><p class="drawer-description">${safe(record.description)}</p><div class="research-grid">${Object.entries(record).filter(([key])=>!['id','name','category','description','sourceUrl'].includes(key)).map(([key,value])=>`<div><span>${safe(key.replace(/([A-Z])/g,' $1'))}</span><strong>${key==='date'?displayDate(value):safe(value)}</strong></div>`).join('')}</div><div class="source-card"><span>PRIMARY SOURCE</span><strong>${safe(record.source)}</strong><p>Open the linked disclosure and confirm all time-sensitive fields before relying on this record.</p><a href="${safe(record.sourceUrl)}" target="_blank" rel="noreferrer">Open source ↗</a></div>`;
  $('#detailDrawer').classList.add('open');$('#drawerBackdrop').classList.add('open');$('#detailDrawer').setAttribute('aria-hidden','false');
}
function closeDrawer(){$('#detailDrawer').classList.remove('open');$('#drawerBackdrop').classList.remove('open');$('#detailDrawer').setAttribute('aria-hidden','true');}
document.addEventListener('click',event=>{
  const viewButton=event.target.closest('[data-view]');if(viewButton){openView(viewButton.dataset.view);return;}
  const sortButton=event.target.closest('[data-sort]');if(sortButton){const key=sortButton.dataset.sort;sortDirection=sortKey===key?-sortDirection:1;sortKey=key;loadModule();return;}
  const row=event.target.closest('[data-record]');if(row)openDrawer(records.find(record=>String(record.id)===row.dataset.record));
});
$('#moduleSearch').addEventListener('input',()=>{clearTimeout(sortTimer);sortTimer=setTimeout(loadModule,250);});$('#moduleFilter').addEventListener('change',loadModule);$('#closeDrawer').onclick=closeDrawer;$('#drawerBackdrop').onclick=closeDrawer;
$('#dismissNotice').onclick=event=>event.currentTarget.parentElement.remove();
$('#exportBtn').onclick=()=>{const config=viewConfig[currentView],csv=[config.headers.map(([,label])=>label),...records.map(record=>config.headers.map(([key])=>record[key]??''))].map(row=>row.map(value=>`"${String(value).replaceAll('"','""')}"`).join(',')).join('\n');const anchor=document.createElement('a');anchor.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));anchor.download=`northstar-${currentView}.csv`;anchor.click();URL.revokeObjectURL(anchor.href);};
$('#addRecord').onclick=()=>showToast('Connect this action to an authenticated PHP write endpoint.');
const dialog=$('#aiDialog');$('#askAi').onclick=()=>dialog.showModal();$('.dialog-close').onclick=()=>dialog.close();
$('#aiForm').addEventListener('submit',event=>{event.preventDefault();dialog.showModal();dialog.querySelector('textarea').value=$('#aiInput').value;dialog.querySelector('textarea').focus();});
$('#dialogForm').addEventListener('submit',event=>{event.preventDefault();dialog.close();showToast('Research queued with source verification enabled.');});
function showToast(message){const toast=$('#toast');toast.textContent=message;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),3200);}
document.querySelectorAll('.suggestions button').forEach(button=>button.onclick=()=>{$('#aiInput').value=button.textContent;$('#aiInput').focus();});
async function loadOverview(){
  try{const payload=await request('companies',{sort:'as_of_date',direction:'desc'});$('#companyRows').innerHTML=payload.records.slice(0,4).map(raw=>{const record=normalize(raw);return `<tr><td class="company-cell"><span class="company-logo">${safe(record.name[0])}</span>${safe(record.name)}</td><td>${safe(record.category)}</td><td><span class="tag">${safe(record.ownership)}</span></td><td>${safe(record.metric)}</td></tr>`;}).join('');}catch{$('#companyRows').innerHTML='<tr><td colspan="4">Connect MySQL to load company records.</td></tr>';}
}
loadOverview();
