import{p as T}from"./chunk-JWPE2WC7-BaiDebsL.js";import{_ as f,N as y,Q as z,d as A,l as B,b as E,a as F,q as P,t as W,g as _,s as N,L,O as M,A as O}from"./mermaid.core-Bhv4aEiF.js";import{p as Y}from"./cynefin-EF2NZ3EQ-CQi1EYZL.js";import"./main-BQyIpp4s.js";import"./jsx-runtime-u17CrQMm.js";/* empty css            */import"./primitives-QlmftP-W.js";import"./index-BsrpIgAq.js";import"./index-C8R8C7Gs.js";import"./index-Bl47hdnY.js";import"./index-DMJe7Hcz.js";import"./client-ISmLB1YQ.js";import"./larecipe-ui--OKxRXaG.js";var I=M.packet,$=class{constructor(){this.packet=[],this.setAccTitle=E,this.getAccTitle=F,this.setDiagramTitle=P,this.getDiagramTitle=W,this.getAccDescription=_,this.setAccDescription=N}static{f(this,"PacketDB")}getConfig(){const t=y({...I,...L().packet});return t.showBits&&(t.paddingY+=10),t}getPacket(){return this.packet}pushWord(t){t.length>0&&this.packet.push(t)}clear(){O(),this.packet=[]}},j=1e4,q=f((t,e)=>{T(t,e);let r=-1,o=[],s=1;const{bitsPerRow:l}=e.getConfig();for(let{start:a,end:i,bits:d,label:g}of t.blocks){if(a!==void 0&&i!==void 0&&i<a)throw new Error(`Packet block ${a} - ${i} is invalid. End must be greater than start.`);if(a??=r+1,a!==r+1)throw new Error(`Packet block ${a} - ${i??a} is not contiguous. It should start from ${r+1}.`);if(d===0)throw new Error(`Packet block ${a} is invalid. Cannot have a zero bit field.`);for(i??=a+(d??1)-1,d??=i-a+1,r=i,B.debug(`Packet block ${a} - ${r} with label ${g}`);o.length<=l+1&&e.getPacket().length<j;){const[c,p]=G({start:a,end:i,bits:d,label:g},s,l);if(o.push(c),c.end+1===s*l&&(e.pushWord(o),o=[],s++),!p)break;({start:a,end:i,bits:d,label:g}=p)}}e.pushWord(o)},"populate"),G=f((t,e,r)=>{if(t.start===void 0)throw new Error("start should have been set during first phase");if(t.end===void 0)throw new Error("end should have been set during first phase");if(t.start>t.end)throw new Error(`Block start ${t.start} is greater than block end ${t.end}.`);if(t.end+1<=e*r)return[t,void 0];const o=e*r-1,s=e*r;return[{start:t.start,end:o,label:t.label,bits:o-t.start},{start:s,end:t.end,label:t.label,bits:t.end-s}]},"getNextFittingBlock"),C={parser:{yy:void 0},parse:f(async t=>{const e=await Y("packet",t),r=C.parser?.yy;if(!(r instanceof $))throw new Error("parser.parser?.yy was not a PacketDB. This is due to a bug within Mermaid, please report this issue at https://github.com/mermaid-js/mermaid/issues.");B.debug(e),q(e,r)},"parse")},H=f((t,e,r,o)=>{const s=o.db,l=s.getConfig(),{rowHeight:a,paddingY:i,bitWidth:d,bitsPerRow:g}=l,c=s.getPacket(),p=s.getDiagramTitle(),u=a+i,n=u*(c.length+1)-(p?0:a),h=d*g+2,k=z(e);k.attr("viewBox",`0 0 ${h} ${n}`),A(k,n,h,l.useMaxWidth);for(const[v,m]of c.entries())K(k,m,v,l);k.append("text").text(p).attr("x",h/2).attr("y",n-u/2).attr("dominant-baseline","middle").attr("text-anchor","middle").attr("class","packetTitle")},"draw"),K=f((t,e,r,{rowHeight:o,paddingX:s,paddingY:l,bitWidth:a,bitsPerRow:i,showBits:d,bitOrder:g})=>{const c=t.append("g"),p=r*(o+l)+l,u=g==="descending";for(const n of e){const h=n.end-n.start+1,k=n.start%i,m=(u?i-k-h:k)*a+1,b=h*a-s;if(c.append("rect").attr("x",m).attr("y",p).attr("width",b).attr("height",o).attr("class","packetBlock"),c.append("text").attr("x",m+b/2).attr("y",p+o/2).attr("class","packetLabel").attr("dominant-baseline","middle").attr("text-anchor","middle").text(n.label),!d)continue;const[S,D]=u?[n.end,n.start]:[n.start,n.end],w=h===1,x=p-2;c.append("text").attr("x",m+(w?b/2:0)).attr("y",x).attr("class","packetByte start").attr("dominant-baseline","auto").attr("text-anchor",w?"middle":"start").text(S),w||c.append("text").attr("x",m+b).attr("y",x).attr("class","packetByte end").attr("dominant-baseline","auto").attr("text-anchor","end").text(D)}},"drawWord"),Q={draw:H},U={byteFontSize:"10px",startByteColor:"black",endByteColor:"black",labelColor:"black",labelFontSize:"12px",titleColor:"black",titleFontSize:"14px",blockStrokeColor:"black",blockStrokeWidth:"1",blockFillColor:"#efefef"},X=f(({packet:t}={})=>{const e=y(U,t);return`
	.packetByte {
		font-size: ${e.byteFontSize};
	}
	.packetByte.start {
		fill: ${e.startByteColor};
	}
	.packetByte.end {
		fill: ${e.endByteColor};
	}
	.packetLabel {
		fill: ${e.labelColor};
		font-size: ${e.labelFontSize};
	}
	.packetTitle {
		fill: ${e.titleColor};
		font-size: ${e.titleFontSize};
	}
	.packetBlock {
		stroke: ${e.blockStrokeColor};
		stroke-width: ${e.blockStrokeWidth};
		fill: ${e.blockFillColor};
	}
	`},"styles"),ct={parser:C,get db(){return new $},renderer:Q,styles:X};export{ct as diagram};
