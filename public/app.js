const sb=supabase.createClient("https://kluipewrewyjseoxnyuc.supabase.co","eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtsdWlwZXdyZXd5anNlb3hueXVjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3OTE0OTg0OTIsImV4cCI6MjEwNzA3NDQ5Mn0.zynIATfgNVXbtxYezQ_xEn2CLjTPDCdjQJtDXMZA0kU");
const $=id=>document.getElementById(id);
const esc=t=>String(t??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const BUCKET="https://kluipewrewyjseoxnyuc.supabase.co/storage/v1/object/public/afbeeldingen/";
const img=u=>typeof u==="string"&&(u.startsWith("data:image/")||u.startsWith(BUCKET))?u:"";
async function naarOpslag(data,map){if(!data||!data.startsWith("data:"))return data||null;
 const blob=await (await fetch(data)).blob(),ext=blob.type==="image/png"?"png":"jpg",pad=map+"/"+Date.now()+"-"+Math.random().toString(36).slice(2,8)+"."+ext;
 const r=await sb.storage.from("afbeeldingen").upload(pad,blob,{contentType:blob.type,cacheControl:"31536000"});
 if(r.error)throw r.error;return sb.storage.from("afbeeldingen").getPublicUrl(pad).data.publicUrl;}
const NL=(a,b)=>a.localeCompare(b,"nl");
const MAIN=["Roosendaal","Bergen op Zoom"];
const S={view:"klant",stad:"",cat:null,all:false,type:null,fstad:null,keuze:3,plaatsen:[],cats:[],set:{},user:null,admin:false,me:null,tab:"klussen",atab:"klussers",contact:{},pend:null,jData:"",logo:"",selP:new Set(),selT:new Set(),ready:false,slug:"",edit:null};
let tt;const flash=(t,ok)=>{const e=$("toast");e.textContent=t;e.className="";clearTimeout(tt);tt=setTimeout(()=>e.className="hide",ok===false?6000:3500);};
const fail=(e)=>{flash(e?.message||"Er ging iets mis.",false);return null;};
function resize(file,max,type){return new Promise((res,rej)=>{const r=new FileReader();r.onerror=rej;r.onload=()=>{const im=new Image();im.onerror=rej;im.onload=()=>{const k=Math.min(1,max/Math.max(im.width,im.height)),c=document.createElement("canvas");c.width=Math.round(im.width*k);c.height=Math.round(im.height*k);c.getContext("2d").drawImage(im,0,0,c.width,c.height);res(c.toDataURL(type,.8));};im.src=r.result;};r.readAsDataURL(file);});}
const types=()=>S.cats.flatMap(c=>c.klustypen.map(t=>({...t,cat:c})));
const kosten=k=>{if(!S.set.betaalmodel_aan)return 0;const t=types().find(x=>x.naam===k.klustype);return t?.credits_override??S.set.credits_per_klus??1;};
const eur=n=>"\u20ac"+((n*(S.set.prijs_per_credit_cent??250))/100).toFixed(2).replace(".",",");

async function laad(){
 const [p,c,i]=await Promise.all([sb.from("plaatsen").select("id,naam,slug,volgorde").eq("actief",true).order("volgorde").order("naam"),
  sb.from("categorieen").select("id,naam,slug,is_top5,volgorde,klustypen(id,naam,volgorde,credits_override)").eq("actief",true).eq("klustypen.actief",true).order("volgorde"),
  sb.from("instellingen").select("sleutel,waarde")]);
 S.plaatsen=p.data||[];S.cats=(c.data||[]).map(x=>({...x,klustypen:(x.klustypen||[]).sort((a,b)=>a.volgorde-b.volgorde)}));
 S.set=Object.fromEntries((i.data||[]).map(r=>[r.sleutel,r.waarde]));S.ready=true;
}
async function wieBenIk(){
 if(!S.user){S.admin=false;S.me=null;return;}
 const [a,m]=await Promise.all([sb.rpc("is_admin"),sb.from("klussers").select("*").eq("id",S.user.id).maybeSingle()]);
 S.admin=a.data===true;S.me=m.data||null;
}
sb.auth.onAuthStateChange((_e,s)=>{S.user=s?.user||null;setTimeout(async()=>{await wieBenIk();render();},0);});

/* ---------- navigatie ---------- */
function route(){const p=location.hash.replace("#/","").split("/"),h=p[0];S.view=["klusser","admin","chat","mijn","blog","klus"].includes(h)?h:"klant";S.lst=h==="klus"?(p[1]||""):"";S.lcat=h==="klus"?(p[2]||""):"";S.token=h==="chat"?(p[1]||""):"";S.slug=h==="blog"?(p[1]||""):"";clearInterval(S.poll);S.chat=null;document.title="Klusmaat - testversie";}
window.addEventListener("hashchange",()=>{route();render();});
$("logo").onclick=()=>{location.hash="#/";};
function nav(){const v=[["klant","Klant"],["blog","Blog"],["klusser","Klusser"]];if(S.user)v.push(["mijn","Mijn aanvragen"]);if(S.admin)v.push(["admin","Admin"]);
 $("nav").innerHTML=v.map(x=>`<button class="${S.view===x[0]?'on':''}" data-a="nav" data-v="${x[0]}">${x[1]}</button>`).join("");
$("uitlog").hidden=!S.user;}
async function render(){nav();if(!S.ready)return;const m=$("app");
 if(S.view==="klant"){m.innerHTML=vKlant();fillPros();fillHome();}
 else if(S.view==="blog")await vBlog();
else if(S.view==="klus")await vLanding();
else if(S.view==="klusser")await vKlusser();
else if(S.view==="chat"||S.view==="mijn")await vKlantPagina();
 else if(S.view==="admin"){if(!S.admin){m.innerHTML='<div class="panel">Geen toegang.</div>';}else await vAdmin();}}

/* ---------- klant ---------- */
function vKlant(){
 const top=S.cats.filter(c=>c.is_top5),rest=S.cats.filter(c=>!c.is_top5),cs=S.all?[...top,...rest]:top;
 const hoofd=S.plaatsen.filter(p=>MAIN.includes(p.naam)),ander=S.plaatsen.filter(p=>!MAIN.includes(p.naam));
 const sn=S.stad&&S.plaatsen.find(p=>p.id===S.stad)?.naam;
 return `<section class="hero"><div><h1>Een goede klusser, gewoon bij jou in de buurt.</h1><p>Kies je plaats en je klus. Geen account nodig.</p>
 <div class="chips">${hoofd.map(p=>`<span class="chip${S.stad===p.id?' on':''}" data-a="stad" data-v="${p.id}">${esc(p.naam)}</span>`).join("")}
 <details class="more"><summary>${ander.some(p=>p.id===S.stad)?esc(sn):"Andere plaatsen"}</summary><div class="menu">${ander.map(p=>`<span class="${S.stad===p.id?'on':''}" data-a="stad" data-v="${p.id}">${esc(p.naam)}</span>`).join("")}</div></details></div></div>
 <svg viewBox="0 0 400 300"><rect width="400" height="300" fill="#f6a56b"/><circle cx="320" cy="64" r="34" fill="#fff3d6"/><rect y="240" width="400" height="60" fill="#0f6b6b"/><rect x="100" y="125" width="180" height="120" fill="#fff"/><polygon points="85,130 190,52 295,130" fill="#c4452b"/><rect x="170" y="180" width="38" height="65" fill="#0f6b6b"/><rect x="118" y="152" width="34" height="32" fill="#bfe3ea"/><rect x="228" y="152" width="34" height="32" fill="#bfe3ea"/></svg></section>
 <h2>Wat voor klus heb je?</h2>
 <div class="grid">${cs.map(c=>`<button class="tile${S.cat?.id===c.id?' on':''}${c.is_top5?'':' sm'}" data-a="cat" data-v="${c.id}"><b>${esc(c.naam)}</b><span class="mut">${c.klustypen.length} typen</span></button>`).join("")}</div>
 ${S.all?"":'<p><button class="btn ghost" data-a="all" style="margin-top:12px">Alle klussen</button></p>'}
 ${S.cat?`<div class="panel" id="tp"><h3>${esc(S.cat.naam)}: kies een type klus</h3><div class="chips">${S.cat.klustypen.map(t=>`<span class="chip" data-a="typ" data-v="${t.id}">${esc(t.naam)}</span>`).join("")}</div>${S.stad?'':'<p class="mut">Hierna vragen we in welke plaats de klus is.</p>'}</div>`:""}
 <h2>Zo werkt het</h2>
<div class="uspg">
<div class="stap"><div class="nr">1</div><div><b>Vertel wat er moet gebeuren</b><div class="mut">Kies je plaats en het soort klus en beschrijf het in een paar zinnen. Dat kost je twee minuten.</div></div></div>
<div class="stap"><div class="nr">2</div><div><b>Klussers uit de buurt reageren</b><div class="mut">Alleen klussers die in jouw plaats werken en dit soort klussen doen, zien jouw aanvraag.</div></div></div>
<div class="stap"><div class="nr">3</div><div><b>Jij kiest</b><div class="mut">Vergelijk de reacties, stel vragen in de chat en kies zelf wie de klus mag doen.</div></div></div>
</div>
<h2>Waarom Klusmaat?</h2>
<div class="uspg">
<div class="usp"><i>&#127873;</i><b>Gratis voor jou</b>Een klus plaatsen kost niets. Je betaalt alleen de klusser voor het werk, tegen de prijs die jullie afspreken.</div>
<div class="usp"><i>&#128274;</i><b>Geen account nodig</b>Je plaatst je klus zonder te registreren en volgt alles via je persoonlijke link.</div>
<div class="usp"><i>&#127919;</i><b>Jij bepaalt hoeveel reacties</b>Kies of 3 tot 8 klussers mogen reageren. Genoeg keuze, geen telefoon die niet meer stilstaat.</div>
<div class="usp"><i>&#128205;</i><b>Klussers uit de regio</b>Je krijgt reacties van vakmensen uit Roosendaal, Bergen op Zoom en de omgeving, geen onbekenden van ver weg.</div>
<div class="usp"><i>&#9989;</i><b>Handmatig goedgekeurd</b>Elke klusser wordt door ons beoordeeld voordat hij klussen ziet. Na afloop geef jij een beoordeling.</div>
<div class="usp"><i>&#128737;</i><b>Jouw gegevens onder controle</b>Alleen klussers die op jouw klus reageren zien je contactgegevens, en nooit meer dan het aantal dat jij koos.</div>
<div class="usp"><i>&#128172;</i><b>Eerst praten, dan beslissen</b>Stel vragen in een veilige chat. Je bent nergens toe verplicht.</div>
<div class="usp"><i>&#128161;</i><b>Of doe het zelf</b>Op ons blog staan handleidingen voor kleine klussen. Lukt het toch niet, dan plaats je alsnog een klus.</div>
</div>
<div class="band"><div><h2>Klaar om je klus te plaatsen?</h2><p>Kies hierboven je plaats en het soort klus. Het duurt twee minuten.</p></div><button class="btn" data-a="top">Naar boven</button></div>
<h2>${sn?"Klussers in "+esc(sn):"Onze klussers"}</h2><div class="cards" id="pros"><p class="mut">Laden...</p></div>
<h2>Populair in jouw regio</h2>
<div class="chips">${hoofd.flatMap(p=>top.map(c=>`<a class="chip" style="text-decoration:none" href="#/klus/${esc(p.slug)}/${esc(c.slug)}">${esc(c.naam)} in ${esc(p.naam)}</a>`)).join("")}</div>
<div id="bt"></div><div id="faq"></div>`;
}
async function fillPros(catId){
 const [k,kp,pr,ke]=await Promise.all([sb.from("klusser_publiek").select("*"),sb.from("klusser_plaatsen_publiek").select("*"),
  sb.from("portfolio_publiek").select("klusser_id,titel,beeld_pad").order("aangemaakt",{ascending:false}).limit(60),catId?sb.from("klusser_expertise_publiek").select("klusser_id").eq("categorie_id",catId):Promise.resolve({data:null})]);
 const el=$("pros");if(!el)return;
 const pl=Object.fromEntries(S.plaatsen.map(p=>[p.id,p.naam]));
 const lijst=(k.data||[]).filter(x=>(!S.stad||(kp.data||[]).some(r=>r.klusser_id===x.id&&r.plaats_id===S.stad))&&(!catId||(ke.data||[]).some(r=>r.klusser_id===x.id)));
 el.innerHTML=lijst.map(x=>{const mijn=(kp.data||[]).filter(r=>r.klusser_id===x.id).map(r=>pl[r.plaats_id]).filter(Boolean).sort(NL),
  pj=(pr.data||[]).filter(r=>r.klusser_id===x.id).slice(0,3);
  return `<div class="k"><div class="fl" style="flex-wrap:nowrap">${img(x.logo_pad)?`<img class="lg" src="${esc(x.logo_pad)}" alt="">`:`<div class="lg">${esc((x.bedrijfsnaam||"?")[0].toUpperCase())}</div>`}<div><h3 style="font-size:18px">${esc(x.bedrijfsnaam)}</h3>${x.gemiddelde!=null?`<span class="mut">${stars(x.gemiddelde)} ${x.gemiddelde} (${x.aantal_reviews})</span><br>`:""}<span class="mut">${esc(mijn.slice(0,3).join(", "))}${mijn.length>3?" +"+(mijn.length-3):""}</span></div></div>
  ${x.over?`<p class="mut">${esc(x.over.slice(0,200))}</p>`:""}
  ${pj.length?`<div class="thumbs">${pj.map(p=>img(p.beeld_pad)?`<img src="${esc(p.beeld_pad)}" alt="${esc(p.titel)}">`:"").join("")}</div>`:""}</div>`;}).join("")
  ||`<p class="mut">${S.stad?"Nog geen klussers in deze plaats.":"Binnenkort vind je hier de klussers uit de regio."}</p>`;
}
function openF(pid){S.fstad=S.plaatsen.find(p=>p.id===pid);$("pl").value=S.fstad.naam;$("ft").textContent=S.type.naam;$("fs").textContent=S.cat.naam+" \u00b7 "+S.fstad.naam;
 ["m1","m2"].forEach(i=>$(i).className="msg hide");$("s1").classList.remove("hide");$("s2").classList.add("hide");$("s3").classList.add("hide");sw();$("dF").showModal();}
function sw(){$("sw").innerHTML=[3,4,5,6,7,8].map(n=>`<span class="chip${n===S.keuze?' on':''}" data-a="keuze" data-v="${n}">${n}</span>`).join("");}
const er=(id,t)=>{$(id).className="msg";$(id).textContent=t;};
$("nx").onclick=()=>{if($("d").value.trim().length<5)return er("m1","Beschrijf kort wat er moet gebeuren.");$("s1").classList.add("hide");$("s2").classList.remove("hide");};
$("bk").onclick=()=>{$("s2").classList.add("hide");$("s1").classList.remove("hide");};
$("go").onclick=async()=>{const v=i=>$(i).value.trim();
 if(!v("n")||!v("a")||!/^[1-9]\d{3}\s?[A-Za-z]{2}$/.test(v("pc"))||!v("t")||!/.+@.+\..+/.test(v("e")))return er("m2","Vul naam, adres, geldige postcode, telefoon en e-mail in.");
 if(!$("pv").checked)return er("m2","Ga akkoord met de privacyverklaring om door te gaan.");
 $("go").disabled=true;
 const {data,error}=await sb.rpc("maak_aanvraag",{p_plaats:S.fstad.id,p_klustype:S.type.id,p_omschrijving:v("d"),p_periode:$("w").value,p_max:S.keuze,p_naam:v("n"),p_adres:v("a"),p_postcode:v("pc"),p_email:v("e"),p_telefoon:v("t"),p_bel:v("b"),p_privacy:true,p_website:v("hp")});
 $("go").disabled=false;if(error)return er("m2",error.message);
 S.lastToken=data;S.lastEmail=v("e");$("kpw").value="";$("m3").className="msg hide";$("cl").textContent=location.origin+location.pathname+"#/chat/"+data;["d","n","a","pc","t","e","b"].forEach(i=>$(i).value="");$("pv").checked=false;
 $("s2").classList.add("hide");$("s3").classList.remove("hide");};
$("cg").innerHTML="";

/* ---------- klusser ---------- */
async function vKlusser(){const m=$("app");
 if(!S.user){m.innerHTML=`<h2>Klusser</h2><div class="panel" style="max-width:420px"><h3>Inloggen of account maken</h3>
  <label>E-mail</label><input id="le" type="email" autocomplete="email"><label>Wachtwoord (minimaal 8 tekens)</label><input id="lp" type="password" autocomplete="current-password">
  <div class="fl" style="margin-top:14px"><button class="btn" data-a="login">Inloggen</button><button class="btn ghost" data-a="signup">Account maken</button></div></div>`;return;}
 const uit=`<button class="mini" data-a="logout">Uitloggen</button>`;
 if(!S.me){m.innerHTML=`<h2>Aanmelden als klusser</h2><div class="panel" style="max-width:520px"><p class="mut">Na je aanmelding beoordelen wij je gegevens. Zodra je bent goedgekeurd kun je je profiel en portfolio invullen.</p>
  <label>Bedrijfsnaam</label><input id="ab" maxlength="120"><label>Contactpersoon</label><input id="ac" maxlength="120"><div class="row"><div><label>Telefoon</label><input id="at" type="tel"></div><div><label>KvK-nummer (optioneel)</label><input id="ak"></div></div>
  <label>Korte toelichting: wat voor werk doe je?</label><textarea id="ao" rows="3" maxlength="1000"></textarea>
  <div class="fl" style="margin-top:14px"><button class="btn" data-a="aanmeld">Aanmelden</button>${uit}</div></div>`;return;}
 if(S.me.status!=="goedgekeurd"){const t={aangemeld:"Je aanmelding is ontvangen en wacht op goedkeuring.",in_beoordeling:"Je account is in beoordeling. Wij nemen contact met je op.",geblokkeerd:"Je account is geblokkeerd. Neem contact met ons op."}[S.me.status];
  m.innerHTML=`<h2>${esc(S.me.bedrijfsnaam)}</h2><div class="panel"><span class="pill">${esc(S.me.status.replace("_"," "))}</span><p>${t}</p>${S.me.status_reden?`<p class="mut">${esc(S.me.status_reden)}</p>`:""}${uit}</div>`;return;}
 const tabs=[["klussen","Klussen"],["chats","Chats"],["reviews","Reviews"],["profiel","Profiel"],["portfolio","Portfolio"]];
 m.innerHTML=`<div class="fl" style="justify-content:space-between"><h2 style="margin:20px 0 0">${esc(S.me.bedrijfsnaam)} <span class="pill ok">Goedgekeurd</span></h2>${uit}</div>
  <div class="tabs">${tabs.map(t=>`<span class="chip${S.tab===t[0]?' on':''}" data-a="tab" data-v="${t[0]}">${t[1]}</span>`).join("")}</div><div id="tabc"><p class="mut">Laden...</p></div>`;
 ({klussen:tKlussen,chats:tChats,reviews:tReviews,profiel:tProfiel,portfolio:tPortfolio})[S.tab]();}

async function tKlussen(){
 const u=S.user.id,cnt=(t,f)=>{let q=sb.from(t).select("*",{count:"exact",head:true}).eq("klusser_id",u);return f?f(q):q;};
 const [ov,pl,ex,pf]=await Promise.all([sb.rpc("klussen_overzicht"),cnt("klusser_plaatsen"),cnt("klusser_klustypen"),cnt("portfolio_projecten",q=>q.eq("status","gepubliceerd"))]);
 if(ov.error)return fail(ov.error);
 let top="";
 if(S.set.betaalmodel_aan){const s=await sb.from("credit_saldo").select("saldo").eq("klusser_id",u).maybeSingle();S.saldo=s.data?.saldo||0;
  top=`<div class="panel"><b style="font-size:22px">${S.saldo} credits</b><div class="mut">${S.set.credits_per_klus??1} credit per klus &middot; ${eur(1)} per credit &middot; Credits kopen volgt in een latere versie</div></div>`;}
 else top='<div class="panel mut">Het betaalmodel staat uit: alle klusgegevens zijn gratis zichtbaar.</div>';
 const m=S.me,st=[["Bedrijfsprofiel invullen: over ons, telefoon en logo",!!(m.over&&m.telefoon&&m.logo_pad),"profiel"],["Werkgebied kiezen: in welke plaatsen werk je?",(pl.count||0)>0,"profiel"],["Expertises kiezen: welke klussen doe je?",(ex.count||0)>0,"profiel"],["Eerste project in je portfolio publiceren",(pf.count||0)>0,"portfolio"]],
 klaar=st.filter(x=>x[1]).length;
 if(klaar<st.length)top=`<div class="panel"><b>Maak je profiel compleet (${klaar} van ${st.length})</b><div style="height:8px;border-radius:99px;background:var(--line);margin:10px 0"><div style="height:8px;border-radius:99px;background:var(--acc2);width:${klaar/st.length*100}%"></div></div>${st.map(x=>`<div class="fl" style="margin:6px 0"><span class="pill${x[1]?' ok':''}">${x[1]?"Klaar":"Open"}</span><span style="flex:1">${x[0]}</span>${x[1]?"":`<button class="mini" data-a="tab" data-v="${x[2]}">Invullen</button>`}</div>`).join("")}<div class="mut">Zonder werkgebied en expertises zie je geen klussen. Een volledig profiel met portfolio wordt vaker gekozen.</div></div>`+top;
 S.ov=ov.data||[];S.ktop=top;kRender();
}
function kRender(){
 const uniq=f=>[...new Set(S.ov.map(k=>k[f]))].sort(NL),kf=S.kf||(S.kf={p:"",c:""}),ps=uniq("plaats"),cs=uniq("categorie");
 if(kf.p&&!ps.includes(kf.p))kf.p="";if(kf.c&&!cs.includes(kf.c))kf.c="";
 const lijst=S.ov.filter(k=>(!kf.p||k.plaats===kf.p)&&(!kf.c||k.categorie===kf.c));
 const filt=(ps.length>1||cs.length>1)?`<div class="chips" style="margin-bottom:6px">${ps.length>1?ps.map(p=>`<span class="chip${kf.p===p?' on':''}" data-a="kfp" data-v="${esc(p)}">${esc(p)}</span>`).join(""):""}${cs.length>1?cs.map(c=>`<span class="chip${kf.c===c?' on':''}" data-a="kfc" data-v="${esc(c)}">${esc(c)}</span>`).join(""):""}</div>`:"";
 $("tabc").innerHTML=S.ktop+filt+`<div class="cards">${lijst.map(k=>{const c=S.contact[k.id],kk=kosten(k),vol=k.aantal_ontgrendeld>=k.max_klussers&&!k.ontgrendeld,laag=S.set.betaalmodel_aan&&!k.ontgrendeld&&S.saldo<kk;
  return `<div class="k"><div class="mut">${esc(k.plaats)} &middot; ${esc(k.categorie)} &middot; ${esc(k.klustype)}</div><p>${esc(k.omschrijving)}</p><div class="mut">${k.aantal_ontgrendeld} van ${k.max_klussers} klussers hebben gereageerd</div>
  ${c?`<div class="contact"><b>${esc(c.naam)}</b><br>${esc(c.telefoon)} &middot; ${esc(c.email)}<br>${esc(c.adres)}, ${esc(c.postcode)} ${esc(c.woonplaats)}${c.bel_tijdsbestek?`<br>Bellen: ${esc(c.bel_tijdsbestek)}`:""}</div>`:
  `<button class="btn" style="margin-top:10px" data-a="${k.ontgrendeld?'show':'unlock'}" data-v="${k.id}" ${vol||laag?'disabled':''}>${vol?'Vol':laag?'Te weinig credits':k.ontgrendeld?'Gegevens tonen':'Contactgegevens bekijken'+(kk?` (${kk} credit${kk>1?'s':''})`:'')}</button>`}</div>`;}).join("")||'<p class="mut">Geen klussen gevonden voor deze selectie of in jouw plaatsen en diensten. Controleer je profiel.</p>'}</div>`;
}
async function ontgrendel(id){const k=S.ov.find(x=>x.id===id);
 const {data,error}=await sb.rpc("ontgrendel_aanvraag",{p_aanvraag:id,p_verwachte_kosten:k.ontgrendeld?null:kosten(k)});
 if(error)return fail(error);S.contact[id]=data;tKlussen();}
function openU(id){const k=S.ov.find(x=>x.id===id),kk=kosten(k);S.pend=id;
 $("ut").textContent=k.klustype+" in "+k.plaats+": naam, adres, telefoonnummer en e-mailadres van de klant worden zichtbaar.";
 $("uk").innerHTML=`<tr><td>Kosten</td><td><b>${kk} credit${kk>1?"s":""} (${eur(kk)})</b></td></tr><tr><td>Saldo nu</td><td>${S.saldo} credits</td></tr><tr><td>Saldo na akkoord</td><td>${S.saldo-kk} credits</td></tr>`;
 $("um").textContent=k.max_klussers;$("dU").showModal();}

async function tProfiel(){const u=S.user.id;
 const [kp,kt]=await Promise.all([sb.from("klusser_plaatsen").select("plaats_id").eq("klusser_id",u),sb.from("klusser_klustypen").select("klustype_id").eq("klusser_id",u)]);
 S.selP=new Set((kp.data||[]).map(x=>x.plaats_id));S.selT=new Set((kt.data||[]).map(x=>x.klustype_id));S.logo=S.me.logo_pad||"";const m=S.me;
 $("tabc").innerHTML=`<div class="panel"><div class="fl"><div id="plogo">${logoHtml()}</div><div style="flex:1"><label style="margin-top:0">Logo</label><input id="plf" type="file" accept="image/*"></div></div>
 <label>Bedrijfsnaam</label><input id="pn" value="${esc(m.bedrijfsnaam)}" maxlength="120"><div class="row"><div><label>Contactpersoon</label><input id="pc2" value="${esc(m.contactnaam)}"></div><div><label>Telefoon</label><input id="pt" value="${esc(m.telefoon)}"></div></div>
 <div class="row"><div><label>Website</label><input id="pw" value="${esc(m.website)}" placeholder="https://"></div><div><label>KvK-nummer</label><input id="pk" value="${esc(m.kvk)}"></div></div>
 <label>Over ons</label><textarea id="po" rows="4" maxlength="1500">${esc(m.over)}</textarea>
 <label>Plaatsen waar je diensten aanbiedt</label><button class="mini" data-a="pall" type="button">Alles selecteren of wissen</button>
 <div class="chips" id="pwp">${[...S.plaatsen].sort((a,b)=>NL(a.naam,b.naam)).map(p=>`<span class="chip${S.selP.has(p.id)?' on':''}" data-a="tp" data-v="${p.id}">${esc(p.naam)}</span>`).join("")}</div>
 <label>Expertises</label>${[...S.cats].sort((a,b)=>NL(a.naam,b.naam)).map(c=>`<div class="grp"><b>${esc(c.naam)}</b><div class="chips">${[...c.klustypen].sort((a,b)=>NL(a.naam,b.naam)).map(t=>`<span class="chip${S.selT.has(t.id)?' on':''}" data-a="tt" data-v="${t.id}">${esc(t.naam)}</span>`).join("")}</div></div>`).join("")}
 <button class="btn" data-a="psave" style="margin-top:16px">Profiel opslaan</button></div>`;
 $("plf").onchange=async e=>{const f=e.target.files[0];if(!f)return;try{S.logo=await resize(f,240,"image/png");$("plogo").innerHTML=logoHtml();}catch(_){flash("Dit beeld kon niet worden gelezen.",false);}};}
const logoHtml=()=>img(S.logo)?`<img class="lg" src="${esc(S.logo)}" alt="">`:`<div class="lg">${esc((S.me.bedrijfsnaam||"?")[0].toUpperCase())}</div>`;
async function saveProfiel(){const u=S.user.id,v=i=>$(i).value.trim();if(!v("pn"))return flash("Vul je bedrijfsnaam in.",false);
 let logo=S.logo||null;try{logo=await naarOpslag(logo,"klusser/"+u);}catch(e){return fail(e);}S.logo=logo||"";
 const up=await sb.from("klussers").update({bedrijfsnaam:v("pn"),contactnaam:v("pc2"),telefoon:v("pt"),website:v("pw"),kvk:v("pk"),over:v("po"),logo_pad:logo}).eq("id",u);if(up.error)return fail(up.error);
 let r=await sb.from("klusser_plaatsen").delete().eq("klusser_id",u);if(r.error)return fail(r.error);
 if(S.selP.size){r=await sb.from("klusser_plaatsen").insert([...S.selP].map(id=>({klusser_id:u,plaats_id:id})));if(r.error)return fail(r.error);}
 r=await sb.from("klusser_klustypen").delete().eq("klusser_id",u);if(r.error)return fail(r.error);
 if(S.selT.size){r=await sb.from("klusser_klustypen").insert([...S.selT].map(id=>({klusser_id:u,klustype_id:id})));if(r.error)return fail(r.error);}
 await wieBenIk();flash("Profiel opgeslagen.",true);}

async function tPortfolio(){
 const {data,error}=await sb.from("portfolio_projecten").select("*").eq("klusser_id",S.user.id).order("aangemaakt",{ascending:false});if(error)return fail(error);
 $("tabc").innerHTML=`<button class="btn" data-a="padd">+ Voeg project toe</button><div class="panel">${(data||[]).map(p=>{const g=p.status==="gepubliceerd";
  return `<div class="proj">${img(p.beeld_pad)?`<img src="${esc(p.beeld_pad)}" alt="">`:""}<div style="flex:1"><b>${esc(p.titel)}</b><br><span class="pill${g?' ok':''}">${g?"Gepubliceerd":"Concept"}</span></div>${g?"":`<button class="mini" data-a="ppub" data-v="${p.id}">Publiceren</button>`}<button class="mini" data-a="pdel" data-v="${p.id}">Verwijder</button></div>`;}).join("")||'<p class="mut">Nog geen projecten.</p>'}</div>`;}
async function saveProj(status){if(!$("jt").value.trim()||!$("ji").value.trim()){$("jm").className="msg";$("jm").textContent="Titel en informatie zijn verplicht.";return;}
 let bp=null;try{bp=await naarOpslag(S.jData,"klusser/"+S.user.id);}catch(e){$("jm").className="msg";$("jm").textContent=e.message;return;}
 const r=await sb.from("portfolio_projecten").insert({klusser_id:S.user.id,titel:$("jt").value.trim(),info:$("ji").value.trim(),beeld_pad:bp,status});
 if(r.error){$("jm").className="msg";$("jm").textContent=r.error.message;return;}$("dJ").close();tPortfolio();}

/* ---------- admin ---------- */
async function vAdmin(){const tabs=[["klussers","Klussers"],["aanvragen","Aanvragen"],["reviews","Reviews"],["blog","Blog"],["faq","FAQ"],["gebieden","Gebieden"],["instellingen","Instellingen"],["logboek","Logboek"]];
 $("app").innerHTML=`<h2>Admin</h2><div class="tabs">${tabs.map(t=>`<span class="chip${S.atab===t[0]?' on':''}" data-a="atab" data-v="${t[0]}">${t[1]}</span>`).join("")}</div><div id="ac">Laden...</div>`;
 const c=$("ac");
 if(S.atab==="klussers"){
  const d=(await sb.from("klussers").select("*").order("aangemeld_op",{ascending:false})).data||[];
  const n=d.filter(k=>k.status==="in_beoordeling").length;c.innerHTML=(n?`<div class="msg">${n} klusser(s) staan in beoordeling (kwaliteitsdrempel of handmatig).</div>`:"")+`<div class="panel tbl"><table><tr><th>Bedrijf</th><th>Contact</th><th>Status</th><th></th></tr>${d.map(k=>`<tr><td><b>${esc(k.bedrijfsnaam)}</b><br><span class="mut">${esc(k.over||"").slice(0,80)}</span></td><td>${esc(k.contactnaam)}<br>${esc(k.telefoon)}<br>${esc(k.email)}</td><td><span class="pill${k.status==='goedgekeurd'?' ok':''}">${esc(k.status.replace("_"," "))}</span>${k.status_reden?`<br><span class="mut">${esc(k.status_reden)}</span>`:""}</td>
  <td>${["goedgekeurd","in_beoordeling","geblokkeerd"].filter(s=>s!==k.status).map(s=>`<button class="mini" data-a="st" data-id="${k.id}" data-v="${s}">${{goedgekeurd:"Goedkeuren",in_beoordeling:"In beoordeling",geblokkeerd:"Blokkeren"}[s]}</button>`).join(" ")}</td></tr>`).join("")||'<tr><td>Nog geen klussers.</td></tr>'}</table></div>`;}
 if(S.atab==="aanvragen"){const {data,error}=await sb.from("aanvragen").select("id,aangemaakt,status,omschrijving,max_klussers,naam,email,telefoon,straat_huisnr,postcode,woonplaats,klustypen(naam),ontgrendelingen(id)").order("aangemaakt",{ascending:false}).limit(100);if(error)return fail(error);
  c.innerHTML=`<div class="panel tbl"><table><tr><th>Klus</th><th>Klant</th><th>Reacties</th></tr>${(data||[]).map(a=>`<tr><td><b>${esc(a.klustypen?.naam)}</b> in ${esc(a.woonplaats)}<br><span class="mut">${esc(a.omschrijving).slice(0,120)}</span></td><td>${esc(a.naam)}<br>${esc(a.telefoon)}<br>${esc(a.email)}<br>${esc(a.straat_huisnr)}, ${esc(a.postcode)}</td><td>${(a.ontgrendelingen||[]).length}/${a.max_klussers}</td></tr>`).join("")||'<tr><td>Nog geen aanvragen.</td></tr>'}</table></div>`;}
 if(S.atab==="instellingen"){const {data,error}=await sb.from("instellingen").select("*").order("sleutel");if(error)return fail(error);
  c.innerHTML=`<div class="panel"><p class="mut">Wijzig een waarde en klik op Opslaan. Waarden zijn true/false of een getal.</p><p><button class="btn ghost" data-a="hercheck">Kwaliteitscontrole nu uitvoeren</button></p><table>${(data||[]).map(r=>`<tr><td><b>${esc(r.sleutel)}</b><br><span class="mut">${esc(r.omschrijving)}</span></td><td><input id="i_${esc(r.sleutel)}" value="${esc(JSON.stringify(r.waarde))}" style="max-width:160px"></td><td><button class="mini" data-a="isave" data-v="${esc(r.sleutel)}">Opslaan</button></td></tr>`).join("")}</table></div>`;}
 if(S.atab==="reviews")return adminReviews(c);
if(S.atab==="blog"){const {data,error}=await sb.from("blog_artikelen").select("id,slug,titel,rubriek,status,gepubliceerd_op").order("aangemaakt",{ascending:false});if(error)return fail(error);
c.innerHTML=`<button class="btn" data-a="badd">+ Nieuw artikel</button><div class="panel tbl"><table><tr><th>Artikel</th><th>Status</th><th></th></tr>${(data||[]).map(b=>`<tr><td><b>${esc(b.titel)}</b><br><span class="mut">${esc(b.rubriek||"")} &middot; /blog/${esc(b.slug)}</span></td><td><span class="pill${b.status==='gepubliceerd'?' ok':''}">${b.status==='gepubliceerd'?"Gepubliceerd":"Concept"}</span></td><td><button class="mini" data-a="bedit" data-v="${b.id}">Bewerken</button> <a class="mini" href="#/blog/${esc(b.slug)}" style="text-decoration:none">Bekijken</a> <button class="mini" data-a="bdel" data-v="${b.id}">Verwijderen</button></td></tr>`).join("")||'<tr><td>Nog geen artikelen.</td></tr>'}</table></div>`;}
if(S.atab==="faq"){const {data,error}=await sb.from("faq_items").select("*").order("volgorde");if(error)return fail(error);S.faqdata=data||[];
c.innerHTML=`<button class="btn" data-a="qadd">+ Nieuwe vraag</button><div class="panel tbl"><table><tr><th>Vraag</th><th>Zichtbaar</th><th></th></tr>${S.faqdata.map(q=>`<tr><td><b>${esc(q.vraag)}</b><br><span class="mut">${esc(q.antwoord).slice(0,100)}</span></td><td>${q.actief?"Ja":"Nee"}</td><td><button class="mini" data-a="qedit" data-v="${q.id}">Bewerken</button> <button class="mini" data-a="qdel" data-v="${q.id}">Verwijderen</button></td></tr>`).join("")||'<tr><td>Nog geen vragen.</td></tr>'}</table></div>`;}
if(S.atab==="gebieden")return adminGebieden(c);
if(S.atab==="logboek")c.innerHTML=`<div class="panel"><p>Download het volledige logboek met alle acties van klussers als tekstbestand.</p><button class="btn" data-a="dl">Logboek downloaden (.txt)</button></div>`;}

async function adminGebieden(c){
 const [p,ca,kt]=await Promise.all([sb.from("plaatsen").select("*").order("volgorde").order("naam"),sb.from("categorieen").select("*").order("volgorde").order("naam"),sb.from("klustypen").select("*").order("volgorde").order("naam")]);
 const er=p.error||ca.error||kt.error;if(er)return fail(er);
 const inp=(id,v,w)=>`<input id="${id}" value="${esc(v??"")}" style="max-width:${w||200}px">`,chk=(id,v)=>`<input type="checkbox" id="${id}" ${v?"checked":""}>`,
 btn=(t,id)=>`<button class="mini" data-a="gsave" data-t="${t}" data-v="${id}">Opslaan</button> <button class="mini" data-a="gdel" data-t="${t}" data-v="${id}">Verwijderen</button>`,
 add=(t,cid,ph)=>`<div class="fl" style="margin-top:10px"><input id="g_new_${t}${cid?"_"+cid:""}" placeholder="${ph}" style="max-width:240px"><button class="btn" data-a="gadd" data-t="${t}" ${cid?`data-c="${cid}"`:""}>Toevoegen</button></div>`;
 c.innerHTML=`<p class="mut">Wijzigingen zijn direct zichtbaar op de site. Zet iets op niet actief in plaats van te verwijderen als het al is gebruikt.</p>
<div class="panel"><h3 style="font-size:20px">Plaatsen</h3><div class="tbl"><table><tr><th>Naam</th><th>Volgorde</th><th>Actief</th><th></th></tr>${(p.data||[]).map(x=>`<tr><td>${inp(`g_plaatsen_${x.id}_naam`,x.naam)}</td><td>${inp(`g_plaatsen_${x.id}_volgorde`,x.volgorde,70)}</td><td>${chk(`g_plaatsen_${x.id}_actief`,x.actief)}</td><td>${btn("plaatsen",x.id)}</td></tr>`).join("")}</table></div>${add("plaatsen","","Nieuwe plaats")}</div>
<div class="panel"><h3 style="font-size:20px">Categorieën</h3><p class="mut">Op de homepage staan de categorieën met Top 5 aangevinkt. Kies er bij voorkeur 5.</p><div class="tbl"><table><tr><th>Naam</th><th>Top 5</th><th>Volgorde</th><th>Actief</th><th></th></tr>${(ca.data||[]).map(x=>`<tr><td>${inp(`g_categorieen_${x.id}_naam`,x.naam)}</td><td>${chk(`g_categorieen_${x.id}_is_top5`,x.is_top5)}</td><td>${inp(`g_categorieen_${x.id}_volgorde`,x.volgorde,70)}</td><td>${chk(`g_categorieen_${x.id}_actief`,x.actief)}</td><td>${btn("categorieen",x.id)}</td></tr>`).join("")}</table></div>${add("categorieen","","Nieuwe categorie")}</div>
${(ca.data||[]).map(cat=>`<div class="panel"><h3 style="font-size:18px">Typen klus: ${esc(cat.naam)}</h3><div class="tbl"><table><tr><th>Naam</th><th>Credits (leeg = standaard)</th><th>Volgorde</th><th>Actief</th><th></th></tr>${(kt.data||[]).filter(x=>x.categorie_id===cat.id).map(x=>`<tr><td>${inp(`g_klustypen_${x.id}_naam`,x.naam)}</td><td>${inp(`g_klustypen_${x.id}_credits_override`,x.credits_override,70)}</td><td>${inp(`g_klustypen_${x.id}_volgorde`,x.volgorde,70)}</td><td>${chk(`g_klustypen_${x.id}_actief`,x.actief)}</td><td>${btn("klustypen",x.id)}</td></tr>`).join("")}</table></div>${add("klustypen",cat.id,"Nieuw type klus")}</div>`).join("")}`;}

/* ---------- acties ---------- */
document.addEventListener("click",async e=>{
 if(e.target.closest("[data-close]"))return e.target.closest("dialog").close();
 const a=e.target.closest("[data-a]");if(!a)return;const v=a.dataset.v,act=a.dataset.a;
 if(act==="nav"){location.hash="#/"+(v==="klant"?"":v);return;}
 if(act==="stad"){S.stad=S.stad===v?"":v;const d=document.querySelector("details.more");if(d)d.open=false;return render();}
 if(act==="cat"){S.cat=S.cats.find(c=>c.id===v);render();$("tp")?.scrollIntoView({block:"nearest"});return;}
 if(act==="all"){S.all=true;return render();}
 if(act==="typ"){S.type=types().find(t=>t.id===v);if(S.stad)openF(S.stad);else{$("cg").innerHTML=[...S.plaatsen].sort((x,y)=>(MAIN.includes(y.naam)-MAIN.includes(x.naam))||NL(x.naam,y.naam)).map(p=>`<span class="chip" data-a="pick" data-v="${p.id}">${esc(p.naam)}</span>`).join("");$("dC").showModal();}return;}
 if(act==="pick"){$("dC").close();return openF(v);}
 if(act==="keuze"){S.keuze=+v;return sw();}
 if(act==="login"||act==="signup"){const em=$("le").value.trim(),pw=$("lp").value;if(!em||pw.length<8)return flash("Vul je e-mail en een wachtwoord van minimaal 8 tekens in.",false);
  const r=act==="login"?await sb.auth.signInWithPassword({email:em,password:pw}):await sb.auth.signUp({email:em,password:pw});
  if(r.error)return fail(r.error);if(act==="signup"&&!r.data.session)flash("Account gemaakt. Bevestig je e-mailadres en log daarna in.",true);return;}
 if(act==="logout"){await sb.auth.signOut();S.contact={};S.chat=null;clearInterval(S.poll);location.hash="#/";return;}
 if(act==="aanmeld"){const v2=i=>$(i).value.trim();if(!v2("ab"))return flash("Vul je bedrijfsnaam in.",false);
  const r=await sb.from("klussers").insert({id:S.user.id,bedrijfsnaam:v2("ab"),contactnaam:v2("ac"),telefoon:v2("at"),kvk:v2("ak"),over:v2("ao"),email:S.user.email});
  if(r.error)return fail(r.error);await wieBenIk();flash("Aanmelding ontvangen.",true);return render();}
 if(act==="tab"){S.tab=v;return render();}
 if(act==="unlock")return S.set.betaalmodel_aan?openU(v):ontgrendel(v);
 if(act==="show")return ontgrendel(v);
 if(act==="doU"){$("dU").close();return ontgrendel(S.pend);}
 if(act==="tp"||act==="tt"){const s=act==="tp"?S.selP:S.selT;s.has(v)?s.delete(v):s.add(v);a.classList.toggle("on");return;}
 if(act==="pall"){const all=S.plaatsen.map(p=>p.id),vol=all.every(i=>S.selP.has(i));S.selP=new Set(vol?[]:all);document.querySelectorAll('#pwp .chip').forEach(c=>c.classList.toggle("on",S.selP.has(c.dataset.v)));return;}
 if(act==="psave")return saveProfiel();
 if(act==="padd"){["jt","ji","jf"].forEach(i=>$(i).value="");S.jData="";$("jprev").className="hide";$("jm").className="msg hide";return $("dJ").showModal();}
 if(act==="jpub")return saveProj("gepubliceerd");if(act==="jdraft")return saveProj("concept");
 if(act==="ppub"){const r=await sb.from("portfolio_projecten").update({status:"gepubliceerd"}).eq("id",v);if(r.error)return fail(r.error);return tPortfolio();}
 if(act==="pdel"){if(!confirm("Project verwijderen?"))return;const r=await sb.from("portfolio_projecten").delete().eq("id",v);if(r.error)return fail(r.error);return tPortfolio();}
 if(act==="kfp"||act==="kfc"){const f=S.kf||(S.kf={p:"",c:""}),k=act==="kfp"?"p":"c";f[k]=f[k]===v?"":v;return kRender();}
 if(act==="gsave"){const t=a.dataset.t,g=f=>$("g_"+t+"_"+v+"_"+f),iv=(f,d)=>{const n=parseInt(g(f).value,10);return Number.isFinite(n)?n:d;},naam=g("naam").value.trim();
  if(!naam)return flash("Vul een naam in.",false);
  const row=t==="plaatsen"?{naam,volgorde:iv("volgorde",100),actief:g("actief").checked}:t==="categorieen"?{naam,is_top5:g("is_top5").checked,volgorde:iv("volgorde",100),actief:g("actief").checked}:{naam,credits_override:g("credits_override").value.trim()===""?null:Math.max(0,iv("credits_override",0)),volgorde:iv("volgorde",100),actief:g("actief").checked};
  const r=await sb.from(t).update(row).eq("id",v);if(r.error)return fail(r.error.code==="23505"?{message:"Deze naam bestaat al."}:r.error);await laad();return flash("Opgeslagen.",true);}
 if(act==="gadd"){const t=a.dataset.t,cid=a.dataset.c,naam=$("g_new_"+t+(cid?"_"+cid:"")).value.trim(),slug=slugify(naam);
  if(!naam||!slug)return flash("Vul een naam in.",false);
  const r=await sb.from(t).insert(t==="klustypen"?{categorie_id:cid,naam,slug}:{naam,slug});if(r.error)return fail(r.error.code==="23505"?{message:"Deze naam bestaat al."}:r.error);await laad();flash("Toegevoegd.",true);return vAdmin();}
 if(act==="gdel"){const t=a.dataset.t;let tekst="Dit item verwijderen? Gebruik bij twijfel liever niet actief.";
  if(t==="categorieen"){const c=await sb.from("klustypen").select("*",{count:"exact",head:true}).eq("categorie_id",v);tekst="Deze categorie verwijderen"+(c.count?" samen met de "+c.count+" typen klus erin":"")+"? Bestaande aanvragen en klusser-expertises in deze categorie kunnen dan niet meer worden getoond. Zet de categorie bij twijfel liever op niet actief.";}
  if(!confirm(tekst))return;
  const r=await sb.from(t).delete().eq("id",v);if(r.error)return fail(r.error.code==="23503"?{message:"Dit item is in gebruik en kan niet worden verwijderd. Zet het op niet actief."}:r.error);await laad();flash("Verwijderd.",true);return vAdmin();}
 if(act==="atab"){S.atab=v;return render();}
 if(act==="st"){const r=await sb.from("klussers").update({status:v}).eq("id",a.dataset.id);if(r.error)return fail(r.error);flash("Status bijgewerkt.",true);return vAdmin();}
 if(act==="isave"){let w;try{w=JSON.parse($("i_"+v).value);}catch(_){return flash("Ongeldige waarde. Gebruik true, false, een getal of tekst tussen aanhalingstekens.",false);}
  const r=await sb.from("instellingen").update({waarde:w,bijgewerkt:new Date().toISOString()}).eq("sleutel",v);if(r.error)return fail(r.error);await laad();return flash("Opgeslagen.",true);}
 if(act==="dl"){const r=await sb.rpc("logboek_export");if(r.error)return fail(r.error);const b=new Blob([r.data||"(leeg)"],{type:"text/plain;charset=utf-8"}),l=document.createElement("a");
  l.href=URL.createObjectURL(b);l.download="klusmaat-logboek-"+new Date().toISOString().slice(0,10)+".txt";document.body.appendChild(l);l.click();l.remove();return;}
});
$("bf").onchange=async e=>{const f=e.target.files[0];if(!f)return;try{S.bimg=await resize(f,900,"image/jpeg");$("bprev").src=S.bimg;$("bprev").className="";}catch(_){flash("Dit beeld kon niet worden gelezen.",false);}};
$("jf").onchange=async e=>{const f=e.target.files[0];if(!f)return;try{S.jData=await resize(f,640,"image/jpeg");$("jprev").src=S.jData;$("jprev").className="";}catch(_){flash("Dit beeld kon niet worden gelezen.",false);}};


/* ===== chat, reviews, kwaliteit ===== */

const md=t=>{const inl=s=>esc(s).replace(/\*\*(.+?)\*\*/g,"<b>$1</b>");
return String(t||"").replace(/\r/g,"").split(/\n{2,}/).map(bl=>{const ls=bl.split("\n").filter(x=>x.trim());if(!ls.length)return "";
if(ls.length===1&&ls[0].startsWith("## "))return "<h3>"+inl(ls[0].slice(3))+"</h3>";
let out="",i=0;while(i<ls.length){const l=ls[i];
if(l.startsWith("## ")){out+="<h3>"+inl(l.slice(3))+"</h3>";i++;}
else if(l.startsWith("- ")){let li="";while(i<ls.length&&ls[i].startsWith("- ")){li+="<li>"+inl(ls[i].slice(2))+"</li>";i++;}out+="<ul>"+li+"</ul>";}
else if(/^\d+\.\s/.test(l)){let li="";while(i<ls.length&&/^\d+\.\s/.test(ls[i])){li+="<li>"+inl(ls[i].replace(/^\d+\.\s/,""))+"</li>";i++;}out+="<ol>"+li+"</ol>";}
else{let p=[];while(i<ls.length&&!ls[i].startsWith("## ")&&!ls[i].startsWith("- ")&&!/^\d+\.\s/.test(ls[i])){p.push(inl(ls[i]));i++;}out+="<p>"+p.join("<br>")+"</p>";}}
return out;}).join("");};
const slugify=t=>String(t||"").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"").replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"");
const blogCard=b=>`<a class="k" href="#/blog/${esc(b.slug)}">${img(b.beeld_pad)?`<img class="bc" src="${esc(b.beeld_pad)}" alt="">`:`<div class="bc">${esc(b.rubriek||"Handleiding")}</div>`}<span class="pill">${esc(b.rubriek||"Handleiding")}</span><h3 style="font-size:19px;margin:8px 0 4px">${esc(b.titel)}</h3><div class="mut">${esc(b.samenvatting||"")}</div></a>`;
async function fillHome(){
const [b,f]=await Promise.all([sb.from("blog_artikelen").select("slug,titel,rubriek,samenvatting,beeld_pad").order("gepubliceerd_op",{ascending:false}).limit(3),
sb.from("faq_items").select("vraag,antwoord").order("volgorde")]);
const bt=$("bt"),fq=$("faq");
if(bt)bt.innerHTML=(b.data&&b.data.length)?`<h2>Zelf aan de slag? Uit ons blog</h2><div class="cards">${b.data.map(blogCard).join("")}</div><p><a href="#/blog" class="btn ghost" style="display:inline-block;text-decoration:none;margin-top:12px">Alle artikelen</a></p>`:"";
if(fq)fq.innerHTML=(f.data&&f.data.length)?`<h2>Veelgestelde vragen</h2><div class="faq">${f.data.map(x=>`<details><summary>${esc(x.vraag)}</summary><p>${esc(x.antwoord)}</p></details>`).join("")}</div>`:"";}
async function vLanding(){const m=$("app");
const st=S.plaatsen.find(p=>p.slug===S.lst),ct=S.cats.find(c=>c.slug===S.lcat);
if(!st||!ct){m.innerHTML='<div class="panel">Deze pagina bestaat niet. <a href="#/">Naar de homepage</a></div>';return;}
document.title=ct.naam+" in "+st.naam+" - Klusmaat";S.stad=st.id;
const hoofd=S.plaatsen.filter(p=>MAIN.includes(p.naam));
m.innerHTML=`<p><a href="#/">&larr; Naar de homepage</a></p>
<section class="hero"><div><h1>${esc(ct.naam)} in ${esc(st.naam)}</h1><p>Zoek je hulp bij ${esc(ct.naam.toLowerCase())} in ${esc(st.naam)}? Plaats gratis je klus en ontvang reacties van klussers uit ${esc(st.naam)} en omgeving. Geen account nodig en je bepaalt zelf met wie je in zee gaat.</p></div>
<div class="panel" style="margin:0"><b>Kies het soort klus</b><div class="chips">${ct.klustypen.map(t=>`<span class="chip" data-a="ltyp" data-v="${t.id}">${esc(t.naam)}</span>`).join("")}</div><p class="mut">Daarna vul je in twee korte stappen je gegevens in.</p></div></section>
<h2>Zo werkt het</h2>
<div class="uspg"><div class="stap"><div class="nr">1</div><div><b>Beschrijf de klus</b><div class="mut">Wat moet er gebeuren en wanneer?</div></div></div>
<div class="stap"><div class="nr">2</div><div><b>Klussers reageren</b><div class="mut">Alleen klussers uit de buurt die dit werk doen.</div></div></div>
<div class="stap"><div class="nr">3</div><div><b>Jij kiest</b><div class="mut">Vergelijk, chat en beslis zelf.</div></div></div></div>
<h2>Klussers in ${esc(st.naam)} voor ${esc(ct.naam.toLowerCase())}</h2><div class="cards" id="pros"><p class="mut">Laden...</p></div>
<div id="lb"></div>
<h2>Ook in ${esc(st.naam)}</h2><div class="chips">${S.cats.filter(c=>c.id!==ct.id&&c.is_top5).map(c=>`<a class="chip" style="text-decoration:none" href="#/klus/${esc(st.slug)}/${esc(c.slug)}">${esc(c.naam)}</a>`).join("")}</div>
<h2>${esc(ct.naam)} in de buurt</h2><div class="chips">${hoofd.filter(p=>p.id!==st.id).map(p=>`<a class="chip" style="text-decoration:none" href="#/klus/${esc(p.slug)}/${esc(ct.slug)}">${esc(ct.naam)} in ${esc(p.naam)}</a>`).join("")}</div>
<div id="faq"></div>`;
fillPros(ct.id);fillHome();
const b=await sb.from("blog_artikelen").select("slug,titel,rubriek,samenvatting,beeld_pad").eq("rubriek",ct.naam).order("gepubliceerd_op",{ascending:false}).limit(3);
const el=$("lb");if(el&&b.data&&b.data.length)el.innerHTML=`<h2>Zelf aan de slag met ${esc(ct.naam.toLowerCase())}</h2><div class="cards">${b.data.map(blogCard).join("")}</div>`;}
async function vBlog(){const m=$("app");document.title="Klusmaat - Blog";
if(S.slug){const {data,error}=await sb.from("blog_artikelen").select("*").eq("slug",S.slug).maybeSingle();if(error)return fail(error);
if(!data){m.innerHTML='<div class="panel">Dit artikel bestaat niet (meer). <a href="#/blog">Naar alle artikelen</a></div>';return;}
document.title=data.titel+" - Klusmaat";
m.innerHTML=`<div class="art"><p><a href="#/blog">&larr; Alle artikelen</a></p><span class="pill">${esc(data.rubriek||"Handleiding")}</span><h1>${esc(data.titel)}</h1>${data.status!=="gepubliceerd"?'<p class="mut">Concept: alleen zichtbaar voor beheerders.</p>':""}${img(data.beeld_pad)?`<img class="cover" src="${esc(data.beeld_pad)}" alt="">`:""}${md(data.inhoud)}
<div class="band"><div><h2 style="font-size:22px">Liever laten doen?</h2><p>Plaats gratis je klus en ontvang reacties van klussers uit de buurt.</p></div><a class="btn" href="#/" style="text-decoration:none">Klus plaatsen</a></div></div>`;window.scrollTo(0,0);return;}
const {data,error}=await sb.from("blog_artikelen").select("slug,titel,rubriek,samenvatting,beeld_pad").order("gepubliceerd_op",{ascending:false});if(error)return fail(error);
m.innerHTML=`<h2>Blog: zelf klussen</h2><p class="mut">Handleidingen voor kleine klussen in en om het huis. Lukt het niet? Dan helpen onze klussers je graag.</p><div class="cards">${(data||[]).map(blogCard).join("")||'<p class="mut">Er staan nog geen artikelen.</p>'}</div>`;}
const stars=n=>"\u2605".repeat(Math.round(n))+"\u2606".repeat(5-Math.round(n));
const tok=()=>S.view==="chat"?S.token:null;
const fmt=t=>new Date(t).toLocaleString("nl-NL",{day:"numeric",month:"short",hour:"2-digit",minute:"2-digit"});
async function vKlantPagina(){const m=$("app");
if(S.view==="mijn"&&!S.user){m.innerHTML=`<h2>Mijn aanvragen</h2><div class="panel" style="max-width:420px"><p class="mut">Log in met het account dat je bij je aanvraag hebt gemaakt.</p><label>E-mail</label><input id="le" type="email"><label>Wachtwoord</label><input id="lp" type="password"><div class="fl" style="margin-top:14px"><button class="btn" data-a="login">Inloggen</button></div></div>`;return;}
const {data,error}=await sb.rpc("klant_overzicht",{p_token:tok()});if(error)return fail(error);
S.ovk=data||[];
m.innerHTML=`<h2>${S.view==="chat"?"Jouw aanvraag":"Mijn aanvragen"}</h2>`+(S.view==="chat"?'<p class="mut">Dit is jouw persoonlijke link. Bewaar hem goed: iedereen met deze link heeft toegang.</p>':"")+
(S.ovk.length?S.ovk.map(a=>`<div class="panel"><div class="mut">${esc(a.categorie)} &middot; ${esc(a.klustype)} &middot; ${esc(a.woonplaats)} &middot; ${fmt(a.aangemaakt)}</div><p>${esc(a.omschrijving)}</p>
<b>Reacties van klussers (${a.reacties.length} van maximaal ${a.max_klussers})</b>
${a.reacties.length?a.reacties.map(reactieHtml).join(""):'<p class="mut">Nog geen reacties. Zodra een klusser contact opneemt, zie je dat hier.</p>'}</div>`).join(""):'<div class="panel">Geen aanvraag gevonden. Controleer je link of log in.</div>')+'<div id="chatbox"></div>';
if(S.chat)openChat(S.chat.id,S.chat.rol,true);}
function reactieHtml(r){return `<div class="k" style="margin-top:10px"><div class="fl" style="flex-wrap:nowrap">${img(r.logo)?`<img class="lg" src="${esc(r.logo)}" alt="">`:`<div class="lg">${esc((r.bedrijfsnaam||"?")[0].toUpperCase())}</div>`}<div><b>${esc(r.bedrijfsnaam)}</b> heeft aangegeven mogelijk contact met je op te nemen.<div class="mut">${r.sterren!=null?stars(r.sterren)+" "+r.sterren+" ("+r.aantal_reviews+" reviews)":"Nog te weinig beoordelingen"}</div></div></div>
${r.over?`<p class="mut">${esc(r.over.slice(0,300))}</p>`:""}
<div class="fl" style="margin-top:8px"><button class="btn sm" data-a="chatopen" data-v="${r.chat_id}" data-rol="klant">Chat openen</button>
<button class="mini" data-a="cdemp" data-v="${r.chat_id}" data-g="${r.gedempt?0:1}">${r.gedempt?"Dempen opheffen":"Dempen"}</button>
${r.gesloten?'<span class="pill">Gesloten</span>':`<button class="mini" data-a="csluit" data-v="${r.chat_id}">Chat sluiten</button>`}
${r.heeft_review?'<span class="pill ok">Beoordeeld</span>':`<button class="mini" data-a="rv" data-v="${r.chat_id}" data-n="${esc(r.bedrijfsnaam)}">Klus afgerond? Beoordeel</button>`}</div></div>`;}
function openChat(id,rol,keep){S.chat={id,rol};clearInterval(S.poll);const box=$("chatbox");if(!box)return;
box.innerHTML=`<div class="panel"><h3 style="font-size:18px">Chat</h3><div class="msgs" id="msgs"><p class="mut">Laden...</p></div><div class="fl" style="margin-top:10px;flex-wrap:nowrap"><input id="ctext" maxlength="2000" placeholder="Typ een bericht..."><button class="btn" data-a="csend">Verstuur</button></div></div>`;
laadChat();S.poll=setInterval(laadChat,5000);if(!keep)box.scrollIntoView({block:"nearest"});}
async function laadChat(){if(!S.chat)return;const r=S.chat.rol==="klant"?await sb.rpc("chat_lees",{p_chat:S.chat.id,p_token:tok()}):await sb.rpc("chat_lees_klusser",{p_chat:S.chat.id});
const el=$("msgs");if(!el){clearInterval(S.poll);return;}if(r.error){el.innerHTML='<p class="mut">'+esc(r.error.message)+'</p>';return;}
const h=JSON.stringify(r.data);if(el.dataset.h===h)return;el.dataset.h=h;const ik=S.chat.rol;
el.innerHTML=(r.data||[]).map(b=>`<div class="b${b.afzender===ik?' me':''}">${esc(b.tekst)}<small>${fmt(b.tijd)}</small></div>`).join("")||'<p class="mut">Nog geen berichten. Begin het gesprek.</p>';el.scrollTop=el.scrollHeight;}
async function chatSend(){const t=$("ctext").value.trim();if(!t)return;
const r=S.chat.rol==="klant"?await sb.rpc("chat_stuur_klant",{p_chat:S.chat.id,p_tekst:t,p_token:tok()}):await sb.rpc("chat_stuur_klusser",{p_chat:S.chat.id,p_tekst:t});
if(r.error)return fail(r.error);$("ctext").value="";laadChat();}
async function tChats(){const {data,error}=await sb.rpc("klusser_chats");if(error)return fail(error);
$("tabc").innerHTML=(data&&data.length?data.map(c=>`<div class="k" style="margin-bottom:10px"><b>${esc(c.klustype)}</b> in ${esc(c.plaats)} &middot; ${esc(c.klant)}<div class="mut">${c.aantal} berichten${c.laatste?" &middot; laatste "+fmt(c.laatste):""}${c.gedempt?" &middot; gedempt door klant":""}${c.gesloten?" &middot; gesloten":""}${c.uitgevoerd===false?" &middot; klant geeft aan dat jij deze klus niet hebt uitgevoerd":""}</div><button class="btn sm" style="margin-top:8px" data-a="chatopen" data-v="${c.chat_id}" data-rol="klusser">Chat openen</button></div>`).join(""):'<p class="mut">Nog geen chats. Een chat start zodra je de gegevens van een klus ontgrendelt.</p>')+'<div id="chatbox"></div>';
if(S.chat&&S.chat.rol==="klusser")openChat(S.chat.id,"klusser",true);}
async function tReviews(){const {data:d,error}=await sb.rpc("klusser_reviews");if(error)return fail(error);
$("tabc").innerHTML=`<div class="panel"><b style="font-size:22px">${d.aantal?stars(d.gemiddelde)+" "+d.gemiddelde:"Nog geen reviews"}</b><div class="mut">${d.aantal} review(s). Het gemiddelde telt mee vanaf ${d.minimum} reviews. Onder een gemiddelde van ${d.drempel} wordt je account automatisch in beoordeling gezet.</div></div>`+(d.reviews||[]).map(r=>`<div class="k" style="margin-bottom:10px"><b>${stars(r.sterren)}</b> <span class="mut">${esc(r.klustype)} &middot; ${fmt(r.datum)}</span>${r.toelichting?`<p>${esc(r.toelichting)}</p>`:""}</div>`).join("");}
async function adminReviews(c){const {data,error}=await sb.from("reviews").select("id,sterren,toelichting,zichtbaar,aangemaakt,klussers(bedrijfsnaam),aanvragen(naam,klustypen(naam))").order("aangemaakt",{ascending:false}).limit(200);if(error)return fail(error);S.rvdata=data||[];
c.innerHTML=`<div class="panel tbl"><table><tr><th>Klusser</th><th>Klus en klant</th><th>Review</th><th></th></tr>${S.rvdata.map(r=>`<tr><td>${esc(r.klussers?.bedrijfsnaam)}</td><td>${esc(r.aanvragen?.klustypen?.naam)}<br><span class="mut">${esc(r.aanvragen?.naam)}</span></td><td>${stars(r.sterren)}${r.toelichting?`<br>${esc(r.toelichting)}`:""}<br><span class="pill${r.zichtbaar?' ok':''}">${r.zichtbaar?"Zichtbaar":"Verborgen"}</span></td><td><button class="mini" data-a="rvvis" data-v="${r.id}" data-z="${r.zichtbaar?0:1}">${r.zichtbaar?"Verbergen":"Tonen"}</button> <button class="mini" data-a="rvedit" data-v="${r.id}">Aanpassen</button> <button class="mini" data-a="rvdel" data-v="${r.id}">Verwijderen</button></td></tr>`).join("")||'<tr><td>Nog geen reviews.</td></tr>'}</table></div>`;}
document.addEventListener("click",async e=>{const a=e.target.closest("[data-a]");if(!a)return;const v=a.dataset.v,act=a.dataset.a;
if(act==="ltyp"){S.type=types().find(t=>t.id===v);S.cat=S.type.cat;return openF(S.stad);}
if(act==="top"){window.scrollTo({top:0,behavior:"smooth"});return;}
if(act==="badd"||act==="bedit"){let x={titel:"",slug:"",rubriek:"",status:"concept",samenvatting:"",inhoud:"",beeld_pad:null,id:null};
if(act==="bedit"){const r=await sb.from("blog_artikelen").select("*").eq("id",v).maybeSingle();if(r.error||!r.data)return fail(r.error);x=r.data;}
S.edit=x;S.bimg=x.beeld_pad||"";$("bt1").value=x.titel;$("bs").value=x.slug;$("br").value=x.rubriek||"";$("bst").value=x.status;$("bsa").value=x.samenvatting||"";$("bin").value=x.inhoud;$("bf").value="";
$("bprev").src=img(S.bimg)||"";$("bprev").className=img(S.bimg)?"":"hide";$("bm").className="msg hide";return $("dB").showModal();}
if(act==="bsave"){const t=$("bt1").value.trim(),sl=($("bs").value.trim()||slugify(t)),bm=(x)=>{$("bm").className="msg";$("bm").textContent=x;};
if(!t||!$("bin").value.trim())return bm("Titel en inhoud zijn verplicht.");if(!/^[a-z0-9]+(-[a-z0-9]+)*$/.test(sl))return bm("Het adres mag alleen kleine letters, cijfers en streepjes bevatten.");
const row={titel:t,slug:sl,rubriek:$("br").value.trim()||null,status:$("bst").value,samenvatting:$("bsa").value.trim()||null,inhoud:$("bin").value,beeld_pad:null};
try{row.beeld_pad=await naarOpslag(S.bimg,"blog");}catch(e){return bm(e.message);}
const r=S.edit&&S.edit.id?await sb.from("blog_artikelen").update(row).eq("id",S.edit.id):await sb.from("blog_artikelen").insert(row);
if(r.error)return bm(r.error.code==="23505"?"Dit adres is al in gebruik.":r.error.message);$("dB").close();flash("Artikel opgeslagen.",true);return vAdmin();}
if(act==="bdel"){if(!confirm("Artikel verwijderen?"))return;const r=await sb.from("blog_artikelen").delete().eq("id",v);if(r.error)return fail(r.error);return vAdmin();}
if(act==="qadd"||act==="qedit"){const x=act==="qedit"?S.faqdata.find(q=>q.id===v):{vraag:"",antwoord:"",volgorde:100,actief:true,id:null};S.edit=x;
$("qv").value=x.vraag;$("qa").value=x.antwoord;$("qo").value=x.volgorde;$("qz").value=x.actief?"1":"0";$("qm").className="msg hide";return $("dQ").showModal();}
if(act==="qsave"){if(!$("qv").value.trim()||!$("qa").value.trim()){$("qm").className="msg";$("qm").textContent="Vraag en antwoord zijn verplicht.";return;}
const row={vraag:$("qv").value.trim(),antwoord:$("qa").value.trim(),volgorde:parseInt($("qo").value,10)||100,actief:$("qz").value==="1"};
const r=S.edit&&S.edit.id?await sb.from("faq_items").update(row).eq("id",S.edit.id):await sb.from("faq_items").insert(row);
if(r.error){$("qm").className="msg";$("qm").textContent=r.error.message;return;}$("dQ").close();flash("Opgeslagen.",true);return vAdmin();}
if(act==="qdel"){if(!confirm("Vraag verwijderen?"))return;const r=await sb.from("faq_items").delete().eq("id",v);if(r.error)return fail(r.error);return vAdmin();}
if(act==="kacc"){const pw=$("kpw").value,m=$("m3"),say=(t,ok)=>{m.className="msg"+(ok?" ok":"");m.textContent=t;};
if(pw.length<8)return say("Kies een wachtwoord van minimaal 8 tekens.");
let r=await sb.auth.signUp({email:S.lastEmail,password:pw});
if(r.error&&/already|registered/i.test(r.error.message))r=await sb.auth.signInWithPassword({email:S.lastEmail,password:pw});
if(r.error)return say(r.error.message);
if(!r.data.session)return say("Account gemaakt. Bevestig je e-mailadres en log daarna in. Je persoonlijke link blijft werken.",true);
const c=await sb.rpc("claim_aanvraag",{p_token:S.lastToken});if(c.error)return say(c.error.message);
say("Account gemaakt. Je aanvraag staat nu ook onder Mijn aanvragen.",true);return;}
if(act==="kgeen"){$("dF").close();location.hash="#/chat/"+S.lastToken;return;}
if(act==="chatopen"){S.chat={id:v,rol:a.dataset.rol};return openChat(v,a.dataset.rol);}
if(act==="csend")return chatSend();
if(act==="cdemp"){const r=await sb.rpc("chat_instellen",{p_chat:v,p_gedempt:a.dataset.g==="1",p_token:tok()});if(r.error)return fail(r.error);return vKlantPagina();}
if(act==="csluit"){if(!confirm("Chat sluiten? Er kunnen dan geen berichten meer worden gestuurd."))return;const r=await sb.rpc("chat_instellen",{p_chat:v,p_gesloten:true,p_token:tok()});if(r.error)return fail(r.error);return vKlantPagina();}
if(act==="rv"){S.rv={chat:v,sterren:0};$("rn").textContent=a.dataset.n;$("r1").classList.remove("hide");$("r2").classList.add("hide");$("rm").className="msg hide";$("rt").value="";return $("dR").showModal();}
if(act==="rja"){$("r1").classList.add("hide");$("r2").classList.remove("hide");$("rst").innerHTML=[1,2,3,4,5].map(n=>`<span class="chip" data-a="rstar" data-v="${n}">${"\u2605".repeat(n)}</span>`).join("");return;}
if(act==="rstar"){S.rv.sterren=+v;document.querySelectorAll("#rst .chip").forEach(c=>c.classList.toggle("on",+c.dataset.v<=S.rv.sterren));return;}
if(act==="rnee"){const r=await sb.rpc("review_plaats",{p_chat:S.rv.chat,p_uitgevoerd:false,p_token:tok()});if(r.error)return fail(r.error);$("dR").close();flash("Bedankt, we hebben je antwoord genoteerd.",true);return vKlantPagina();}
if(act==="rsend"){if(!S.rv.sterren){$("rm").className="msg";$("rm").textContent="Kies eerst het aantal sterren.";return;}
const r=await sb.rpc("review_plaats",{p_chat:S.rv.chat,p_uitgevoerd:true,p_sterren:S.rv.sterren,p_toelichting:$("rt").value.trim(),p_token:tok()});
if(r.error){$("rm").className="msg";$("rm").textContent=r.error.message;return;}$("dR").close();flash("Bedankt voor je beoordeling.",true);return vKlantPagina();}
if(act==="rvvis"){const r=await sb.from("reviews").update({zichtbaar:a.dataset.z==="1"}).eq("id",v);if(r.error)return fail(r.error);return vAdmin();}
if(act==="rvedit"){const x=S.rvdata.find(q=>q.id===v),s=prompt("Aantal sterren (1 tot 5):",x.sterren);if(s===null)return;const n=parseInt(s,10);if(!(n>=1&&n<=5))return flash("Kies 1 tot 5.",false);
const t=prompt("Toelichting (leeg laten voor geen):",x.toelichting||"");if(t===null)return;const r=await sb.from("reviews").update({sterren:n,toelichting:t.trim()||null}).eq("id",v);if(r.error)return fail(r.error);return vAdmin();}
if(act==="rvdel"){if(!confirm("Review verwijderen?"))return;const r=await sb.from("reviews").delete().eq("id",v);if(r.error)return fail(r.error);return vAdmin();}
if(act==="hercheck"){const r=await sb.rpc("kwaliteit_hercontrole");if(r.error)return fail(r.error);return flash("Controle klaar. Automatisch in beoordeling: "+r.data,true);}
});
document.addEventListener("keydown",e=>{if(e.key==="Enter"&&e.target.id==="ctext"){e.preventDefault();chatSend();}});
(async()=>{route();try{await laad();const {data}=await sb.auth.getSession();S.user=data.session?.user||null;await wieBenIk();}catch(e){$("app").innerHTML='<div class="panel">De verbinding met de database mislukte. Probeer het later opnieuw.</div>';return;}render();})();
