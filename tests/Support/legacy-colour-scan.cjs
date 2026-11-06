// Phase 10: lists rendered fixtures that still show the legacy violet (#6D28D9 family) on visible elements.
// node tests/Support/legacy-colour-scan.cjs /existing/playwright-core /path/to/public /path/to/fixtures [...more fixture dirs]
// CHROMIUM_PATH (optional) points at a browser executable. Fixtures are served locally; nothing is sent anywhere.
const {chromium}=require(process.argv[2]);const fs=require('fs'),path=require('path');
const pub=process.argv[3];const dirs=process.argv.slice(4);
(async()=>{const b=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});const c=await b.newContext();
let cur;await c.route('http://reservebot.test/**',r=>{const rel=decodeURIComponent(new URL(r.request().url()).pathname).slice(1);const f=/^(css|js|fonts)\//.test(rel)?path.join(pub,rel):path.join(cur,rel);try{r.fulfill({body:fs.readFileSync(f),contentType:f.endsWith('.css')?'text/css':f.endsWith('.js')?'text/javascript':f.endsWith('.woff2')?'font/woff2':'text/html'})}catch(e){r.fulfill({status:404,body:''})}});
const p=await c.newPage();await p.setViewportSize({width:1438,height:900});const out={};
for(const d of dirs){cur=d;for(const n of fs.readdirSync(d).filter(f=>f.endsWith('.html'))){await p.goto('http://reservebot.test/'+n);
const hits=await p.evaluate(()=>{const isV=c=>{const m=c.match(/[\d.]+/g);if(!m)return false;const [r,g,bl]=m.map(Number);const a=m[3]===undefined?1:+m[3];return a>0&&bl>r+40&&bl>g+60&&r>g+10};
const s=new Set();for(const e of document.querySelectorAll('body *')){const r=e.getBoundingClientRect();if(!r.width)continue;const cs=getComputedStyle(e);for(const k of ['color','backgroundColor','borderTopColor','outlineColor','accentColor'])if((k!=='borderTopColor'||cs.borderTopStyle!=='none')&&(k!=='outlineColor'||cs.outlineStyle!=='none')&&isV(cs[k]))s.add(k+':'+cs[k]+' '+e.tagName+'.'+(e.className||'').toString().slice(0,30));}return [...s].slice(0,5)});
if(hits.length)(out[path.basename(d)+'/'+n]=hits)}}
const keys=Object.keys(out);console.log(keys.length,'documents with violet-ish colours');process.exitCode=keys.length?1:0;for(const k of keys.slice(0,40))console.log(k,JSON.stringify(out[k]));await b.close()})();
