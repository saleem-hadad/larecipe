import{p as I}from"./chunk-JWPE2WC7-Dr2cWM0R.js";import{s as _,g as E,t as F,q as D,a as P,b as z,_ as c,Q as G,A as B,N as w,L as C,O as W,l as b,R as V,d as H}from"./mermaid.core-C1BZ5GmA.js";import{p as j}from"./cynefin-EF2NZ3EQ-ZpI5vkjv.js";import"./main-BsuLryVK.js";import"./jsx-runtime-u17CrQMm.js";/* empty css            */import"./primitives-QlmftP-W.js";import"./index-BsrpIgAq.js";import"./index-C8R8C7Gs.js";import"./index-Bl47hdnY.js";import"./index-DMJe7Hcz.js";import"./client-ISmLB1YQ.js";import"./larecipe-ui--OKxRXaG.js";var x={showLegend:!0,ticks:5,max:null,min:0,graticule:"circle"},y=32,A={axes:[],curves:[],options:x},g=structuredClone(A),N=W.radar,U=c(()=>w({...N,...C().radar}),"getConfig"),M=c(()=>g.axes,"getAxes"),X=c(()=>g.curves,"getCurves"),q=c(()=>g.options,"getOptions"),K=c(a=>{g.axes=a.map(t=>({name:t.name,label:t.label??t.name}))},"setAxes"),Q=c(a=>{g.curves=a.map(t=>({name:t.name,label:t.label??t.name,entries:Y(t.entries)}))},"setCurves"),Y=c(a=>{if(a[0].axis==null)return a.map(e=>e.value);const t=M();if(t.length===0)throw new Error("Axes must be populated before curves for reference entries");return t.map(e=>{const r=a.find(s=>s.axis?.$refText===e.name);if(r===void 0)throw new Error("Missing entry for axis "+e.label);return r.value})},"computeCurveEntries"),Z=c(a=>{const t=a.reduce((e,r)=>(e[r.name]=r,e),{});g.options={showLegend:t.showLegend?.value??x.showLegend,ticks:t.ticks?.value??x.ticks,max:t.max?.value??x.max,min:t.min?.value??x.min,graticule:t.graticule?.value??x.graticule},g.options.ticks>y&&(b.warn(`Radar diagram ticks (${g.options.ticks}) exceeds maximum allowed (${y}). Using ${y} instead.`),g.options.ticks=y)},"setOptions"),J=c(()=>{B(),g=structuredClone(A)},"clear"),$={getAxes:M,getCurves:X,getOptions:q,setAxes:K,setCurves:Q,setOptions:Z,getConfig:U,clear:J,setAccTitle:z,getAccTitle:P,setDiagramTitle:D,getDiagramTitle:F,getAccDescription:E,setAccDescription:_},tt=c(a=>{I(a,$);const{axes:t,curves:e,options:r}=a;$.setAxes(t),$.setCurves(e),$.setOptions(r)},"populate"),et={parse:c(async a=>{const t=await j("radar",a);b.debug(t),tt(t)},"parse")},at=c((a,t,e,r)=>{const s=r.db,i=s.getAxes(),l=s.getCurves(),n=s.getOptions(),o=s.getConfig(),d=s.getDiagramTitle(),u=G(t),p=rt(u,o),m=n.max??Math.max(...l.map(f=>Math.max(...f.entries))),h=n.min,v=Math.min(o.width,o.height)/2;st(p,i,v,n.ticks,n.graticule),nt(p,i,v,o),L(p,i,l,h,m,n.graticule,o),k(p,l,n.showLegend,o),p.append("text").attr("class","radarTitle").text(d).attr("x",0).attr("y",-o.height/2-o.marginTop)},"draw"),rt=c((a,t)=>{const e=t.width+t.marginLeft+t.marginRight,r=t.height+t.marginTop+t.marginBottom,s={x:t.marginLeft+t.width/2,y:t.marginTop+t.height/2};return H(a,r,e,t.useMaxWidth??!0),a.attr("viewBox",`0 0 ${e} ${r}`).attr("overflow","visible"),a.append("g").attr("transform",`translate(${s.x}, ${s.y})`)},"drawFrame"),st=c((a,t,e,r,s)=>{if(s==="circle")for(let i=0;i<r;i++){const l=e*(i+1)/r;a.append("circle").attr("r",l).attr("class","radarGraticule")}else if(s==="polygon"){const i=t.length;for(let l=0;l<r;l++){const n=e*(l+1)/r,o=t.map((d,u)=>{const p=2*u*Math.PI/i-Math.PI/2,m=n*Math.cos(p),h=n*Math.sin(p);return`${m},${h}`}).join(" ");a.append("polygon").attr("points",o).attr("class","radarGraticule")}}},"drawGraticule"),nt=c((a,t,e,r)=>{const s=t.length;for(let i=0;i<s;i++){const l=t[i].label,n=2*i*Math.PI/s-Math.PI/2,o=Math.cos(n),d=Math.sin(n);a.append("line").attr("x1",0).attr("y1",0).attr("x2",e*r.axisScaleFactor*o).attr("y2",e*r.axisScaleFactor*d).attr("class","radarAxisLine");const u=o>.01?"start":o<-.01?"end":"middle",p=d>.01?"hanging":d<-.01?"auto":"central",m=4;a.append("text").text(l).attr("x",e*r.axisLabelFactor*o+m*o).attr("y",e*r.axisLabelFactor*d+m*d).attr("text-anchor",u).attr("dominant-baseline",p).attr("class","radarAxisLabel")}},"drawAxes");function L(a,t,e,r,s,i,l){const n=t.length,o=Math.min(l.width,l.height)/2;e.forEach((d,u)=>{if(d.entries.length!==n)return;const p=d.entries.map((m,h)=>{const v=2*Math.PI*h/n-Math.PI/2,f=T(m,r,s,o),O=f*Math.cos(v),R=f*Math.sin(v);return{x:O,y:R}});i==="circle"?a.append("path").attr("d",S(p,l.curveTension)).attr("class",`radarCurve-${u}`):i==="polygon"&&a.append("polygon").attr("points",p.map(m=>`${m.x},${m.y}`).join(" ")).attr("class",`radarCurve-${u}`)})}c(L,"drawCurves");function T(a,t,e,r){const s=Math.min(Math.max(a,t),e);return r*(s-t)/(e-t)}c(T,"relativeRadius");function S(a,t){const e=a.length;let r=`M${a[0].x},${a[0].y}`;for(let s=0;s<e;s++){const i=a[(s-1+e)%e],l=a[s],n=a[(s+1)%e],o=a[(s+2)%e],d={x:l.x+(n.x-i.x)*t,y:l.y+(n.y-i.y)*t},u={x:n.x-(o.x-l.x)*t,y:n.y-(o.y-l.y)*t};r+=` C${d.x},${d.y} ${u.x},${u.y} ${n.x},${n.y}`}return`${r} Z`}c(S,"closedRoundCurve");function k(a,t,e,r){if(!e)return;const s=(r.width/2+r.marginRight)*3/4,i=-(r.height/2+r.marginTop)*3/4,l=20;t.forEach((n,o)=>{const d=a.append("g").attr("transform",`translate(${s}, ${i+o*l})`);d.append("rect").attr("width",12).attr("height",12).attr("class",`radarLegendBox-${o}`),d.append("text").attr("x",16).attr("y",0).attr("class","radarLegendText").text(n.label)})}c(k,"drawLegend");var ot={draw:at},it=c((a,t)=>{let e="";for(let r=0;r<a.THEME_COLOR_LIMIT;r++){const s=a[`cScale${r}`];e+=`
		.radarCurve-${r} {
			color: ${s};
			fill: ${s};
			fill-opacity: ${t.curveOpacity};
			stroke: ${s};
			stroke-width: ${t.curveStrokeWidth};
		}
		.radarLegendBox-${r} {
			fill: ${s};
			fill-opacity: ${t.curveOpacity};
			stroke: ${s};
		}
		`}return e},"genIndexStyles"),lt=c(a=>{const t=V(),e=C(),r=w(t,e.themeVariables),s=w(r.radar,a);return{themeVariables:r,radarOptions:s}},"buildRadarStyleOptions"),ct=c(({radar:a}={})=>{const{themeVariables:t,radarOptions:e}=lt(a);return`
	.radarTitle {
		font-size: ${t.fontSize};
		color: ${t.titleColor};
		dominant-baseline: hanging;
		text-anchor: middle;
	}
	.radarAxisLine {
		stroke: ${e.axisColor};
		stroke-width: ${e.axisStrokeWidth};
	}
	.radarAxisLabel {
		font-size: ${e.axisLabelFontSize}px;
		color: ${e.axisColor};
	}
	.radarGraticule {
		fill: ${e.graticuleColor};
		fill-opacity: ${e.graticuleOpacity};
		stroke: ${e.graticuleColor};
		stroke-width: ${e.graticuleStrokeWidth};
	}
	.radarLegendText {
		text-anchor: start;
		font-size: ${e.legendFontSize}px;
		dominant-baseline: hanging;
	}
	${it(t,e)}
	`},"styles"),bt={parser:et,db:$,renderer:ot,styles:ct};export{bt as diagram};
