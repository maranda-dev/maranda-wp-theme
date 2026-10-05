const categoryIcons = {
  CHAUSSEE:'<path d="M8 21 10 3M16 21 14 3M12 6v3m0 3v3m0 3v3"/>',
  SIGNALISATION:'<path d="M12 3v18M6 6h12l-2 5H6z"/>',
  MARQUAGE:'<path d="M4 18 9 6h6l5 12M8 14h8"/>',
  DRAINAGE:'<path d="M4 9h16M6 13h12M8 17h8M7 5h10"/>',
  DISPOSITIF_RETENUE:'<path d="M4 9h16M6 9v10m12-10v10M4 14h16"/>',
  STRUCTURES:'<path d="M3 19h18M5 19V9l7-5 7 5v10M8 19v-6h8v6"/>',
  MAINTIEN_CIRCULATION:'<path d="m12 3 9 16H3zM12 9v4m0 3h.01"/>',
  AUTRE:'<circle cx="12" cy="12" r="8"/><path d="M12 8h.01M11 12h1v4h1"/>'
};
const groups = [
  {name:'Chaussée et ouvrages',categories:['CHAUSSEE','STRUCTURES','DRAINAGE','DISPOSITIF_RETENUE']},
  {name:'Signalisation et circulation',categories:['SIGNALISATION','MARQUAGE','MAINTIEN_CIRCULATION']},
  {name:'Autres relevés',categories:['AUTRE']}
];
const svg = key => `<svg viewBox="0 0 24 24" aria-hidden="true">${categoryIcons[key] || categoryIcons.AUTRE}</svg>`;
const el = (tag,text,className) => { const node=document.createElement(tag); if(text!==undefined) node.textContent=text; if(className) node.className=className; return node; };
const allowedPublicUrl = (value,type) => {
  if(typeof value!=='string') return '';
  try {
    const url=new URL(value);
    if(url.protocol!=='https:'||url.username||url.password||url.search||url.hash) return '';
    const reportHost=['rapport.bsir.ca','rapport.reseau-routier.ca'].includes(url.hostname) && (/^\/[ABCDEFGHJKMNPQRSTVWXYZ23456789]{6}$/.test(url.pathname)||/^\/public\/[0-9a-f-]{36}$/.test(url.pathname));
    const mediaHost=['inspekt.mariomaranda.ca','inspekt.reseau-routier.ca','inspekt.maranda.io','inspekt.bsir.ca'].includes(url.hostname) && /^\/api\/public\/map\/photos\/[a-zA-Z0-9-]+\.webp$/.test(url.pathname);
    return (type==='report'?reportHost:mediaHost)?url.href:'';
  } catch { return ''; }
};

function validFeature(feature) {
  if (!feature || feature.type!=='Feature' || !['Point','LineString'].includes(feature.geometry?.type)) return null;
  const p=feature.properties || {}, isCoord=c=>Array.isArray(c)&&c.length===2&&c.every(Number.isFinite)&&Math.abs(c[0])<=180&&Math.abs(c[1])<=90;
  const coords=feature.geometry.coordinates, isLine=feature.geometry.type==='LineString';
  if (isLine ? !Array.isArray(coords)||coords.length<2||!coords.every(isCoord) : !isCoord(coords)) return null;
  if (!['publicId','title','category','categoryLabel','stage','stageLabel','reportUrl'].every(k=>typeof p[k]==='string'&&p[k].trim())) return null;
  const reportUrl=allowedPublicUrl(p.reportUrl,'report');if(!reportUrl)return null;
  const anchor=isLine?coords[0]:coords;
  return {...p,reportUrl,photoUrl:allowedPublicUrl(p.photoUrl,'photo'),coordinates:coords,geometryType:isLine?'LINE':'POINT',latitude:anchor[1],longitude:anchor[0]};
}

document.querySelectorAll('[data-inspekt-map]').forEach(root => {
  if(root.dataset.initialized || !window.L) return;
  root.dataset.initialized='true';
  const L=window.L, canvas=root.querySelector('[data-canvas]'), status=root.querySelector('[data-status]'), detail=root.querySelector('[data-detail]'), groupsHost=root.querySelector('[data-groups]'), count=root.querySelector('[data-count]');
  const sidebar=root.querySelector('.rr-map-sidebar'), scrim=root.querySelector('[data-scrim]'), mobileToggle=root.querySelector('[data-sidebar-toggle]');
  const map=L.map(canvas,{zoomControl:false,scrollWheelZoom:true});
  L.control.zoom({position:'topright'}).addTo(map);
  const plan=L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).addTo(map);
  const satellite=L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{maxZoom:19,attribution:'Tiles &copy; Esri'});
  const markerLayer=L.layerGroup().addTo(map);
  let reports=[], selectedId='', previousFocus=null;
  const visible=new Set();

  const closeSidebar=()=>{sidebar.classList.remove('is-open');scrim.hidden=true;mobileToggle.setAttribute('aria-expanded','false');};
  mobileToggle.addEventListener('click',()=>{const open=!sidebar.classList.contains('is-open');sidebar.classList.toggle('is-open',open);scrim.hidden=!open;mobileToggle.setAttribute('aria-expanded',String(open));});
  scrim.addEventListener('click',closeSidebar);

  root.querySelectorAll('[data-basemap]').forEach(button=>button.addEventListener('click',()=>{
    const useSatellite=button.dataset.basemap==='satellite';
    if(useSatellite){map.removeLayer(plan);satellite.addTo(map);}else{map.removeLayer(satellite);plan.addTo(map);}
    root.querySelectorAll('[data-basemap]').forEach(item=>{const active=item===button;item.classList.toggle('is-active',active);item.setAttribute('aria-pressed',String(active));});
  }));

  function closeDetail(){ detail.hidden=true; selectedId=''; previousFocus?.focus?.({preventScroll:true}); }
  function showDetail(report,focusClose=false){
    selectedId=report.publicId; detail.replaceChildren();
    const head=el('header',undefined,'rr-map-detail__head'), close=el('button','×','rr-map-detail__close');
    close.type='button';close.setAttribute('aria-label','Fermer la fiche');close.addEventListener('click',closeDetail);
    head.append(el('small','Nature du relevé'),el('strong',report.categoryLabel),close);detail.append(head);
    if(report.photoUrl){const image=el('img');image.src=report.photoUrl;image.alt='';image.loading='lazy';image.addEventListener('error',()=>image.remove(),{once:true});detail.append(image);}
    const body=el('div',undefined,'rr-map-detail__body');body.append(el('h2',report.title));
    if(report.summary) body.append(el('p',report.summary));
    const context=[report.municipalityOrSector,report.date,report.rtss].filter(Boolean).join(' · ');if(context) body.append(el('p',context,'rr-map-detail__meta'));
    body.append(el('span',report.stageLabel,'rr-map-detail__stage'));
    const link=el('a','Voir le rapport public →','rr-map-detail__link');link.href=report.reportUrl;link.target='_blank';link.rel='noopener noreferrer';body.append(link);
    detail.append(body);detail.hidden=false;if(focusClose)close.focus({preventScroll:true});
  }

  function renderGroups(){
    groupsHost.replaceChildren();
    const available=new Map(reports.map(r=>[r.category,r.categoryLabel]));
    const configured=new Set(groups.flatMap(group=>group.categories));
    const actual=[...groups];
    const extras=[...available.keys()].filter(key=>!configured.has(key));if(extras.length)actual.push({name:'Autres catégories',categories:extras});
    actual.forEach(group=>{
      const cats=group.categories.filter(key=>available.has(key));if(!cats.length)return;
      const section=el('section',undefined,'rr-map-group'),heading=el('h2',group.name,'rr-map-group__heading'),items=el('div',undefined,'rr-map-group__items');
      cats.forEach(key=>{visible.add(key);const button=el('button',undefined,'rr-map-filter is-active');button.type='button';button.dataset.category=key;button.setAttribute('aria-pressed','true');const icon=el('span',undefined,'rr-map-filter__icon');icon.innerHTML=svg(key);button.append(icon,el('span',available.get(key)),el('span',String(reports.filter(r=>r.category===key).length),'rr-map-filter__count'));button.addEventListener('click',()=>{visible.has(key)?visible.delete(key):visible.add(key);button.classList.toggle('is-active',visible.has(key));button.setAttribute('aria-pressed',String(visible.has(key)));renderMarkers();});items.append(button);});
      section.append(heading,items);groupsHost.append(section);
    });
  }

  function renderMarkers(){
    markerLayer.clearLayers();const shown=reports.filter(r=>visible.has(r.category));
    shown.forEach(report=>{
      if(report.geometryType==='LINE'){
        const latlngs=report.coordinates.map(([lng,lat])=>[lat,lng]);
        const line=L.polyline(latlngs,{color:'#095797',weight:7,opacity:.92,lineCap:'round',lineJoin:'round'}).bindTooltip(`${report.categoryLabel} — ${report.title}`,{sticky:true});
        line.on('click',e=>{previousFocus=e.originalEvent?.target;showDetail(report,true);}).addTo(markerLayer);
        [['Début',latlngs[0]],['Fin',latlngs[latlngs.length-1]]].forEach(([label,latlng])=>{
          L.circleMarker(latlng,{radius:5,color:'#fff',weight:2,fillColor:'#095797',fillOpacity:1,interactive:false})
            .bindTooltip(label,{permanent:true,direction:'top',offset:[0,-7],className:'rr-map-endpoint-label'})
            .addTo(markerLayer);
        });
        return;
      }
      const icon=L.divIcon({html:`<span class="rr-map-marker__pin">${svg(report.category)}</span>`,className:'rr-map-marker',iconSize:[44,48],iconAnchor:[22,44]});
      const marker=L.marker([report.latitude,report.longitude],{icon,title:`${report.categoryLabel} — ${report.title}`,alt:`${report.categoryLabel} — ${report.title}`}).bindTooltip(report.title,{direction:'top',offset:[0,-38]});
      marker.on('click',()=>{previousFocus=marker.getElement();showDetail(report,true);}).addTo(markerLayer);
    });
    count.textContent=`${shown.length} relevé${shown.length>1?'s':''} affiché${shown.length>1?'s':''}`;
    if(selectedId&&!shown.some(r=>r.publicId===selectedId))closeDetail();
  }

  root.querySelector('[data-reset]').addEventListener('click',()=>{reports.forEach(r=>visible.add(r.category));root.querySelectorAll('.rr-map-filter').forEach(b=>{b.classList.add('is-active');b.setAttribute('aria-pressed','true');});renderMarkers();});
  detail.addEventListener('keydown',event=>{if(event.key==='Escape')closeDetail();});
  root.querySelector('[data-fullscreen]').addEventListener('click',async()=>{try{document.fullscreenElement?await document.exitFullscreen():await root.requestFullscreen();}catch{root.classList.toggle('is-pseudo-fullscreen');}});

  async function load(){
    try{
      const url=new URL(root.dataset.endpoint,location.origin);url.searchParams.set('limit','250');
      const response=await fetch(url,{credentials:'omit'});if(!response.ok)throw new Error();
      const data=await response.json();if(data?.type!=='FeatureCollection'||!Array.isArray(data.features))throw new Error();
      reports=data.features.map(validFeature).filter(Boolean);renderGroups();renderMarkers();status.hidden=reports.length>0;status.textContent='Aucun relevé public disponible pour le moment.';
      const points=reports.flatMap(r=>r.geometryType==='LINE'?r.coordinates.map(([lng,lat])=>[lat,lng]):[[r.latitude,r.longitude]]);
      points.length?map.fitBounds(L.latLngBounds(points),{padding:[55,55],maxZoom:13}):map.fitWorld();
    }catch{status.hidden=false;status.textContent='Les relevés sont temporairement indisponibles.';map.fitWorld();}
  }
  new ResizeObserver(()=>map.invalidateSize()).observe(canvas);load();
});
