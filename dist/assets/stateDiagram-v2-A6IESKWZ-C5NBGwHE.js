import{g as se}from"./chunk-XXDRQBXY-MLGY2Fkf.js";import{s as ie}from"./chunk-VQ5W62QI-KUn2RJ1T.js";import{_ as u,l as _,c as $,p as re,r as ae,u as ne,a as oe,b as le,g as ce,s as he,q as de,t as ue,$ as fe,m as U,A as pe,k as kt,z as Se,C as ge,D as ye,E as me,I as Te}from"./mermaid.core-TtSStBC9.js";import{c as Ee}from"./chunk-55XCU76K-CJf4zj1I.js";import"./main-B_Jc-vtd.js";import"./jsx-runtime-u17CrQMm.js";/* empty css            */import"./primitives-QlmftP-W.js";import"./index-BsrpIgAq.js";import"./index-C8R8C7Gs.js";import"./index-Bl47hdnY.js";import"./index-DMJe7Hcz.js";import"./client-ISmLB1YQ.js";import"./larecipe-ui--OKxRXaG.js";var Ct=(function(){var t=u(function(V,o,d,r){for(d=d||{},r=V.length;r--;d[V[r]]=o);return d},"o"),e=[1,2],n=[1,3],s=[1,4],l=[2,4],c=[1,9],p=[1,11],S=[1,16],a=[1,17],m=[1,18],T=[1,19],x=[1,33],L=[1,20],R=[1,21],v=[1,22],f=[1,23],w=[1,24],D=[1,26],F=[1,27],I=[1,28],P=[1,29],k=[1,30],H=[1,31],rt=[1,32],at=[1,35],nt=[1,36],ot=[1,37],lt=[1,38],z=[1,34],g=[1,4,5,16,17,19,21,22,24,25,26,27,28,29,33,35,37,38,41,45,48,51,52,53,54,57],ct=[1,4,5,14,15,16,17,19,21,22,24,25,26,27,28,29,33,35,37,38,39,40,41,45,48,51,52,53,54,57],wt=[4,5,16,17,19,21,22,24,25,26,27,28,29,33,35,37,38,41,45,48,51,52,53,54,57],yt={trace:u(function(){},"trace"),yy:{},symbols_:{error:2,start:3,SPACE:4,NL:5,SD:6,document:7,line:8,statement:9,classDefStatement:10,styleStatement:11,cssClassStatement:12,idStatement:13,DESCR:14,"-->":15,HIDE_EMPTY:16,scale:17,WIDTH:18,COMPOSIT_STATE:19,STRUCT_START:20,STRUCT_STOP:21,STATE_DESCR:22,AS:23,ID:24,FORK:25,JOIN:26,CHOICE:27,CONCURRENT:28,note:29,notePosition:30,NOTE_TEXT:31,direction:32,acc_title:33,acc_title_value:34,acc_descr:35,acc_descr_value:36,acc_descr_multiline_value:37,CLICK:38,STRING:39,HREF:40,classDef:41,CLASSDEF_ID:42,CLASSDEF_STYLEOPTS:43,DEFAULT:44,style:45,STYLE_IDS:46,STYLEDEF_STYLEOPTS:47,class:48,CLASSENTITY_IDS:49,STYLECLASS:50,direction_tb:51,direction_bt:52,direction_rl:53,direction_lr:54,eol:55,";":56,EDGE_STATE:57,STYLE_SEPARATOR:58,left_of:59,right_of:60,$accept:0,$end:1},terminals_:{2:"error",4:"SPACE",5:"NL",6:"SD",14:"DESCR",15:"-->",16:"HIDE_EMPTY",17:"scale",18:"WIDTH",19:"COMPOSIT_STATE",20:"STRUCT_START",21:"STRUCT_STOP",22:"STATE_DESCR",23:"AS",24:"ID",25:"FORK",26:"JOIN",27:"CHOICE",28:"CONCURRENT",29:"note",31:"NOTE_TEXT",33:"acc_title",34:"acc_title_value",35:"acc_descr",36:"acc_descr_value",37:"acc_descr_multiline_value",38:"CLICK",39:"STRING",40:"HREF",41:"classDef",42:"CLASSDEF_ID",43:"CLASSDEF_STYLEOPTS",44:"DEFAULT",45:"style",46:"STYLE_IDS",47:"STYLEDEF_STYLEOPTS",48:"class",49:"CLASSENTITY_IDS",50:"STYLECLASS",51:"direction_tb",52:"direction_bt",53:"direction_rl",54:"direction_lr",56:";",57:"EDGE_STATE",58:"STYLE_SEPARATOR",59:"left_of",60:"right_of"},productions_:[0,[3,2],[3,2],[3,2],[7,0],[7,2],[8,2],[8,1],[8,1],[9,1],[9,1],[9,1],[9,1],[9,2],[9,3],[9,4],[9,1],[9,2],[9,1],[9,4],[9,3],[9,6],[9,1],[9,1],[9,1],[9,1],[9,4],[9,4],[9,1],[9,2],[9,2],[9,1],[9,5],[9,5],[10,3],[10,3],[11,3],[12,3],[32,1],[32,1],[32,1],[32,1],[55,1],[55,1],[13,1],[13,1],[13,3],[13,3],[30,1],[30,1]],performAction:u(function(o,d,r,y,E,i,B){var h=i.length-1;switch(E){case 3:return y.setRootDoc(i[h]),i[h];case 4:this.$=[];break;case 5:i[h]!="nl"&&(i[h-1].push(i[h]),this.$=i[h-1]);break;case 6:case 7:this.$=i[h];break;case 8:this.$="nl";break;case 12:this.$=i[h];break;case 13:const Q=i[h-1];Q.description=y.trimColon(i[h]),this.$=Q;break;case 14:this.$={stmt:"relation",state1:i[h-2],state2:i[h]};break;case 15:const mt=y.trimColon(i[h]);this.$={stmt:"relation",state1:i[h-3],state2:i[h-1],description:mt};break;case 19:this.$={stmt:"state",id:i[h-3],type:"default",description:"",doc:i[h-1]};break;case 20:var G=i[h],K=i[h-2].trim();if(i[h].match(":")){var dt=i[h].split(":");G=dt[0],K=[K,dt[1]]}this.$={stmt:"state",id:G,type:"default",description:K};break;case 21:this.$={stmt:"state",id:i[h-3],type:"default",description:i[h-5],doc:i[h-1]};break;case 22:this.$={stmt:"state",id:i[h],type:"fork"};break;case 23:this.$={stmt:"state",id:i[h],type:"join"};break;case 24:this.$={stmt:"state",id:i[h],type:"choice"};break;case 25:this.$={stmt:"state",id:y.getDividerId(),type:"divider"};break;case 26:this.$={stmt:"state",id:i[h-1].trim(),note:{position:i[h-2].trim(),text:i[h].trim()}};break;case 29:this.$=i[h].trim(),y.setAccTitle(this.$);break;case 30:case 31:this.$=i[h].trim(),y.setAccDescription(this.$);break;case 32:this.$={stmt:"click",id:i[h-3],url:i[h-2],tooltip:i[h-1]};break;case 33:this.$={stmt:"click",id:i[h-3],url:i[h-1],tooltip:""};break;case 34:case 35:this.$={stmt:"classDef",id:i[h-1].trim(),classes:i[h].trim()};break;case 36:this.$={stmt:"style",id:i[h-1].trim(),styleClass:i[h].trim()};break;case 37:this.$={stmt:"applyClass",id:i[h-1].trim(),styleClass:i[h].trim()};break;case 38:y.setDirection("TB"),this.$={stmt:"dir",value:"TB"};break;case 39:y.setDirection("BT"),this.$={stmt:"dir",value:"BT"};break;case 40:y.setDirection("RL"),this.$={stmt:"dir",value:"RL"};break;case 41:y.setDirection("LR"),this.$={stmt:"dir",value:"LR"};break;case 44:case 45:this.$={stmt:"state",id:i[h].trim(),type:"default",description:""};break;case 46:this.$={stmt:"state",id:i[h-2].trim(),classes:[i[h].trim()],type:"default",description:""};break;case 47:this.$={stmt:"state",id:i[h-2].trim(),classes:[i[h].trim()],type:"default",description:""};break}},"anonymous"),table:[{3:1,4:e,5:n,6:s},{1:[3]},{3:5,4:e,5:n,6:s},{3:6,4:e,5:n,6:s},t([1,4,5,16,17,19,22,24,25,26,27,28,29,33,35,37,38,41,45,48,51,52,53,54,57],l,{7:7}),{1:[2,1]},{1:[2,2]},{1:[2,3],4:c,5:p,8:8,9:10,10:12,11:13,12:14,13:15,16:S,17:a,19:m,22:T,24:x,25:L,26:R,27:v,28:f,29:w,32:25,33:D,35:F,37:I,38:P,41:k,45:H,48:rt,51:at,52:nt,53:ot,54:lt,57:z},t(g,[2,5]),{9:39,10:12,11:13,12:14,13:15,16:S,17:a,19:m,22:T,24:x,25:L,26:R,27:v,28:f,29:w,32:25,33:D,35:F,37:I,38:P,41:k,45:H,48:rt,51:at,52:nt,53:ot,54:lt,57:z},t(g,[2,7]),t(g,[2,8]),t(g,[2,9]),t(g,[2,10]),t(g,[2,11]),t(g,[2,12],{14:[1,40],15:[1,41]}),t(g,[2,16]),{18:[1,42]},t(g,[2,18],{20:[1,43]}),{23:[1,44]},t(g,[2,22]),t(g,[2,23]),t(g,[2,24]),t(g,[2,25]),{30:45,31:[1,46],59:[1,47],60:[1,48]},t(g,[2,28]),{34:[1,49]},{36:[1,50]},t(g,[2,31]),{13:51,24:x,57:z},{42:[1,52],44:[1,53]},{46:[1,54]},{49:[1,55]},t(ct,[2,44],{58:[1,56]}),t(ct,[2,45],{58:[1,57]}),t(g,[2,38]),t(g,[2,39]),t(g,[2,40]),t(g,[2,41]),t(g,[2,6]),t(g,[2,13]),{13:58,24:x,57:z},t(g,[2,17]),t(wt,l,{7:59}),{24:[1,60]},{24:[1,61]},{23:[1,62]},{24:[2,48]},{24:[2,49]},t(g,[2,29]),t(g,[2,30]),{39:[1,63],40:[1,64]},{43:[1,65]},{43:[1,66]},{47:[1,67]},{50:[1,68]},{24:[1,69]},{24:[1,70]},t(g,[2,14],{14:[1,71]}),{4:c,5:p,8:8,9:10,10:12,11:13,12:14,13:15,16:S,17:a,19:m,21:[1,72],22:T,24:x,25:L,26:R,27:v,28:f,29:w,32:25,33:D,35:F,37:I,38:P,41:k,45:H,48:rt,51:at,52:nt,53:ot,54:lt,57:z},t(g,[2,20],{20:[1,73]}),{31:[1,74]},{24:[1,75]},{39:[1,76]},{39:[1,77]},t(g,[2,34]),t(g,[2,35]),t(g,[2,36]),t(g,[2,37]),t(ct,[2,46]),t(ct,[2,47]),t(g,[2,15]),t(g,[2,19]),t(wt,l,{7:78}),t(g,[2,26]),t(g,[2,27]),{5:[1,79]},{5:[1,80]},{4:c,5:p,8:8,9:10,10:12,11:13,12:14,13:15,16:S,17:a,19:m,21:[1,81],22:T,24:x,25:L,26:R,27:v,28:f,29:w,32:25,33:D,35:F,37:I,38:P,41:k,45:H,48:rt,51:at,52:nt,53:ot,54:lt,57:z},t(g,[2,32]),t(g,[2,33]),t(g,[2,21])],defaultActions:{5:[2,1],6:[2,2],47:[2,48],48:[2,49]},parseError:u(function(o,d){if(d.recoverable)this.trace(o);else{var r=new Error(o);throw r.hash=d,r}},"parseError"),parse:u(function(o){var d=this,r=[0],y=[],E=[null],i=[],B=this.table,h="",G=0,K=0,dt=2,Q=1,mt=i.slice.call(arguments,1),b=Object.create(this.lexer),W={yy:{}};for(var Tt in this.yy)Object.prototype.hasOwnProperty.call(this.yy,Tt)&&(W.yy[Tt]=this.yy[Tt]);b.setInput(o,W.yy),W.yy.lexer=b,W.yy.parser=this,typeof b.yylloc>"u"&&(b.yylloc={});var Et=b.yylloc;i.push(Et);var te=b.options&&b.options.ranges;typeof W.yy.parseError=="function"?this.parseError=W.yy.parseError:this.parseError=Object.getPrototypeOf(this).parseError;function ee(N){r.length=r.length-2*N,E.length=E.length-N,i.length=i.length-N}u(ee,"popStack");function It(){var N;return N=y.pop()||b.lex()||Q,typeof N!="number"&&(N instanceof Array&&(y=N,N=y.pop()),N=d.symbols_[N]||N),N}u(It,"lex");for(var A,j,O,_t,X={},ut,Y,Nt,ft;;){if(j=r[r.length-1],this.defaultActions[j]?O=this.defaultActions[j]:((A===null||typeof A>"u")&&(A=It()),O=B[j]&&B[j][A]),typeof O>"u"||!O.length||!O[0]){var bt="";ft=[];for(ut in B[j])this.terminals_[ut]&&ut>dt&&ft.push("'"+this.terminals_[ut]+"'");b.showPosition?bt="Parse error on line "+(G+1)+`:
`+b.showPosition()+`
Expecting `+ft.join(", ")+", got '"+(this.terminals_[A]||A)+"'":bt="Parse error on line "+(G+1)+": Unexpected "+(A==Q?"end of input":"'"+(this.terminals_[A]||A)+"'"),this.parseError(bt,{text:b.match,token:this.terminals_[A]||A,line:b.yylineno,loc:Et,expected:ft})}if(O[0]instanceof Array&&O.length>1)throw new Error("Parse Error: multiple actions possible at state: "+j+", token: "+A);switch(O[0]){case 1:r.push(A),E.push(b.yytext),i.push(b.yylloc),r.push(O[1]),A=null,K=b.yyleng,h=b.yytext,G=b.yylineno,Et=b.yylloc;break;case 2:if(Y=this.productions_[O[1]][1],X.$=E[E.length-Y],X._$={first_line:i[i.length-(Y||1)].first_line,last_line:i[i.length-1].last_line,first_column:i[i.length-(Y||1)].first_column,last_column:i[i.length-1].last_column},te&&(X._$.range=[i[i.length-(Y||1)].range[0],i[i.length-1].range[1]]),_t=this.performAction.apply(X,[h,K,G,W.yy,O[1],E,i].concat(mt)),typeof _t<"u")return _t;Y&&(r=r.slice(0,-1*Y*2),E=E.slice(0,-1*Y),i=i.slice(0,-1*Y)),r.push(this.productions_[O[1]][0]),E.push(X.$),i.push(X._$),Nt=B[r[r.length-2]][r[r.length-1]],r.push(Nt);break;case 3:return!0}}return!0},"parse")},Zt=(function(){var V={EOF:1,parseError:u(function(d,r){if(this.yy.parser)this.yy.parser.parseError(d,r);else throw new Error(d)},"parseError"),setInput:u(function(o,d){return this.yy=d||this.yy||{},this._input=o,this._more=this._backtrack=this.done=!1,this.yylineno=this.yyleng=0,this.yytext=this.matched=this.match="",this.conditionStack=["INITIAL"],this.yylloc={first_line:1,first_column:0,last_line:1,last_column:0},this.options.ranges&&(this.yylloc.range=[0,0]),this.offset=0,this},"setInput"),input:u(function(){var o=this._input[0];this.yytext+=o,this.yyleng++,this.offset++,this.match+=o,this.matched+=o;var d=o.match(/(?:\r\n?|\n).*/g);return d?(this.yylineno++,this.yylloc.last_line++):this.yylloc.last_column++,this.options.ranges&&this.yylloc.range[1]++,this._input=this._input.slice(1),o},"input"),unput:u(function(o){var d=o.length,r=o.split(/(?:\r\n?|\n)/g);this._input=o+this._input,this.yytext=this.yytext.substr(0,this.yytext.length-d),this.offset-=d;var y=this.match.split(/(?:\r\n?|\n)/g);this.match=this.match.substr(0,this.match.length-1),this.matched=this.matched.substr(0,this.matched.length-1),r.length-1&&(this.yylineno-=r.length-1);var E=this.yylloc.range;return this.yylloc={first_line:this.yylloc.first_line,last_line:this.yylineno+1,first_column:this.yylloc.first_column,last_column:r?(r.length===y.length?this.yylloc.first_column:0)+y[y.length-r.length].length-r[0].length:this.yylloc.first_column-d},this.options.ranges&&(this.yylloc.range=[E[0],E[0]+this.yyleng-d]),this.yyleng=this.yytext.length,this},"unput"),more:u(function(){return this._more=!0,this},"more"),reject:u(function(){if(this.options.backtrack_lexer)this._backtrack=!0;else return this.parseError("Lexical error on line "+(this.yylineno+1)+`. You can only invoke reject() in the lexer when the lexer is of the backtracking persuasion (options.backtrack_lexer = true).
`+this.showPosition(),{text:"",token:null,line:this.yylineno});return this},"reject"),less:u(function(o){this.unput(this.match.slice(o))},"less"),pastInput:u(function(){var o=this.matched.substr(0,this.matched.length-this.match.length);return(o.length>20?"...":"")+o.substr(-20).replace(/\n/g,"")},"pastInput"),upcomingInput:u(function(){var o=this.match;return o.length<20&&(o+=this._input.substr(0,20-o.length)),(o.substr(0,20)+(o.length>20?"...":"")).replace(/\n/g,"")},"upcomingInput"),showPosition:u(function(){var o=this.pastInput(),d=new Array(o.length+1).join("-");return o+this.upcomingInput()+`
`+d+"^"},"showPosition"),test_match:u(function(o,d){var r,y,E;if(this.options.backtrack_lexer&&(E={yylineno:this.yylineno,yylloc:{first_line:this.yylloc.first_line,last_line:this.last_line,first_column:this.yylloc.first_column,last_column:this.yylloc.last_column},yytext:this.yytext,match:this.match,matches:this.matches,matched:this.matched,yyleng:this.yyleng,offset:this.offset,_more:this._more,_input:this._input,yy:this.yy,conditionStack:this.conditionStack.slice(0),done:this.done},this.options.ranges&&(E.yylloc.range=this.yylloc.range.slice(0))),y=o[0].match(/(?:\r\n?|\n).*/g),y&&(this.yylineno+=y.length),this.yylloc={first_line:this.yylloc.last_line,last_line:this.yylineno+1,first_column:this.yylloc.last_column,last_column:y?y[y.length-1].length-y[y.length-1].match(/\r?\n?/)[0].length:this.yylloc.last_column+o[0].length},this.yytext+=o[0],this.match+=o[0],this.matches=o,this.yyleng=this.yytext.length,this.options.ranges&&(this.yylloc.range=[this.offset,this.offset+=this.yyleng]),this._more=!1,this._backtrack=!1,this._input=this._input.slice(o[0].length),this.matched+=o[0],r=this.performAction.call(this,this.yy,this,d,this.conditionStack[this.conditionStack.length-1]),this.done&&this._input&&(this.done=!1),r)return r;if(this._backtrack){for(var i in E)this[i]=E[i];return!1}return!1},"test_match"),next:u(function(){if(this.done)return this.EOF;this._input||(this.done=!0);var o,d,r,y;this._more||(this.yytext="",this.match="");for(var E=this._currentRules(),i=0;i<E.length;i++)if(r=this._input.match(this.rules[E[i]]),r&&(!d||r[0].length>d[0].length)){if(d=r,y=i,this.options.backtrack_lexer){if(o=this.test_match(r,E[i]),o!==!1)return o;if(this._backtrack){d=!1;continue}else return!1}else if(!this.options.flex)break}return d?(o=this.test_match(d,E[y]),o!==!1?o:!1):this._input===""?this.EOF:this.parseError("Lexical error on line "+(this.yylineno+1)+`. Unrecognized text.
`+this.showPosition(),{text:"",token:null,line:this.yylineno})},"next"),lex:u(function(){var d=this.next();return d||this.lex()},"lex"),begin:u(function(d){this.conditionStack.push(d)},"begin"),popState:u(function(){var d=this.conditionStack.length-1;return d>0?this.conditionStack.pop():this.conditionStack[0]},"popState"),_currentRules:u(function(){return this.conditionStack.length&&this.conditionStack[this.conditionStack.length-1]?this.conditions[this.conditionStack[this.conditionStack.length-1]].rules:this.conditions.INITIAL.rules},"_currentRules"),topState:u(function(d){return d=this.conditionStack.length-1-Math.abs(d||0),d>=0?this.conditionStack[d]:"INITIAL"},"topState"),pushState:u(function(d){this.begin(d)},"pushState"),stateStackSize:u(function(){return this.conditionStack.length},"stateStackSize"),options:{"case-insensitive":!0},performAction:u(function(d,r,y,E){function i(){const B=r.yytext.indexOf("%%");if(B===0)return!1;if(B>0){const h=r.yytext.slice(0,B),G=r.yytext.slice(B);G&&d.lexer.unput(G),r.yytext=h}return!0}switch(u(i,"processId"),y){case 0:return 38;case 1:return 40;case 2:return 39;case 3:return 44;case 4:return 51;case 5:return 52;case 6:return 53;case 7:return 54;case 8:return 5;case 9:break;case 10:break;case 11:break;case 12:break;case 13:return this.pushState("SCALE"),17;case 14:return 18;case 15:this.popState();break;case 16:return this.begin("acc_title"),33;case 17:return this.popState(),"acc_title_value";case 18:return this.begin("acc_descr"),35;case 19:return this.popState(),"acc_descr_value";case 20:this.begin("acc_descr_multiline");break;case 21:this.popState();break;case 22:return"acc_descr_multiline_value";case 23:return this.pushState("CLASSDEF"),41;case 24:return this.popState(),this.pushState("CLASSDEFID"),"DEFAULT_CLASSDEF_ID";case 25:return this.popState(),this.pushState("CLASSDEFID"),42;case 26:return this.popState(),43;case 27:return this.pushState("CLASS"),48;case 28:return this.popState(),this.pushState("CLASS_STYLE"),49;case 29:return this.popState(),50;case 30:return this.pushState("STYLE"),45;case 31:return this.popState(),this.pushState("STYLEDEF_STYLES"),46;case 32:return this.popState(),47;case 33:return this.pushState("SCALE"),17;case 34:return 18;case 35:this.popState();break;case 36:this.pushState("STATE");break;case 37:return this.popState(),r.yytext=r.yytext.slice(0,-8).trim(),25;case 38:return this.popState(),r.yytext=r.yytext.slice(0,-8).trim(),26;case 39:return this.popState(),r.yytext=r.yytext.slice(0,-10).trim(),27;case 40:return this.popState(),r.yytext=r.yytext.slice(0,-8).trim(),25;case 41:return this.popState(),r.yytext=r.yytext.slice(0,-8).trim(),26;case 42:return this.popState(),r.yytext=r.yytext.slice(0,-10).trim(),27;case 43:return 51;case 44:return 52;case 45:return 53;case 46:return 54;case 47:this.pushState("STATE_STRING");break;case 48:return this.pushState("STATE_ID"),"AS";case 49:return i()?(this.popState(),"ID"):void 0;case 50:this.popState();break;case 51:return"STATE_DESCR";case 52:throw new Error('Error: State name must be a single word. Found: "'+r.yytext.trim()+'"');case 53:return 19;case 54:this.popState();break;case 55:return this.popState(),this.pushState("struct"),20;case 56:return this.popState(),21;case 57:break;case 58:return this.begin("NOTE"),29;case 59:return this.popState(),this.pushState("NOTE_ID"),59;case 60:return this.popState(),this.pushState("NOTE_ID"),60;case 61:this.popState(),this.pushState("FLOATING_NOTE");break;case 62:return this.popState(),this.pushState("FLOATING_NOTE_ID"),"AS";case 63:break;case 64:return"NOTE_TEXT";case 65:return i()?(this.popState(),"ID"):void 0;case 66:return i()?(this.popState(),this.pushState("NOTE_TEXT"),24):void 0;case 67:return this.popState(),r.yytext=r.yytext.substr(2).trim(),31;case 68:return this.popState(),r.yytext=r.yytext.slice(0,-8).trim(),31;case 69:return 6;case 70:return 6;case 71:return 16;case 72:return 57;case 73:return i()?24:void 0;case 74:return r.yytext=r.yytext.trim(),14;case 75:return 15;case 76:return 28;case 77:return 58;case 78:return 5;case 79:return"INVALID"}},"anonymous"),rules:[/^(?:click\b)/i,/^(?:href\b)/i,/^(?:"[^"]*")/i,/^(?:default\b)/i,/^(?:.*direction\s+TB[^\n]*)/i,/^(?:.*direction\s+BT[^\n]*)/i,/^(?:.*direction\s+RL[^\n]*)/i,/^(?:.*direction\s+LR[^\n]*)/i,/^(?:[\n]+)/i,/^(?:[\s]+)/i,/^(?:((?!\n)\s)+)/i,/^(?:#[^\n]*)/i,/^(?:%%(?!\{)[^\n]*)/i,/^(?:scale\s+)/i,/^(?:\d+)/i,/^(?:\s+width\b)/i,/^(?:accTitle\s*:\s*)/i,/^(?:(?!\n||)*[^\n]*)/i,/^(?:accDescr\s*:\s*)/i,/^(?:(?!\n||)*[^\n]*)/i,/^(?:accDescr\s*\{\s*)/i,/^(?:[\}])/i,/^(?:[^\}]*)/i,/^(?:classDef\s+)/i,/^(?:DEFAULT\s+)/i,/^(?:\w+\s+)/i,/^(?:[^\n]*)/i,/^(?:class\s+)/i,/^(?:(\w+)+((,\s*\w+)*))/i,/^(?:[^\n]*)/i,/^(?:style\s+)/i,/^(?:[\w,]+\s+)/i,/^(?:[^\n]*)/i,/^(?:scale\s+)/i,/^(?:\d+)/i,/^(?:\s+width\b)/i,/^(?:state\s+)/i,/^(?:.*<<fork>>)/i,/^(?:.*<<join>>)/i,/^(?:.*<<choice>>)/i,/^(?:.*\[\[fork\]\])/i,/^(?:.*\[\[join\]\])/i,/^(?:.*\[\[choice\]\])/i,/^(?:.*direction\s+TB[^\n]*)/i,/^(?:.*direction\s+BT[^\n]*)/i,/^(?:.*direction\s+RL[^\n]*)/i,/^(?:.*direction\s+LR[^\n]*)/i,/^(?:["])/i,/^(?:\s*as\s+)/i,/^(?:[^\n\{]*)/i,/^(?:["])/i,/^(?:[^"]*)/i,/^(?:\w+\s+\w+.*?\{)/i,/^(?:[^\n\s\{]+)/i,/^(?:\n)/i,/^(?:\{)/i,/^(?:\})/i,/^(?:[\n])/i,/^(?:note\s+)/i,/^(?:left of\b)/i,/^(?:right of\b)/i,/^(?:")/i,/^(?:\s*as\s*)/i,/^(?:["])/i,/^(?:[^"]*)/i,/^(?:[^\n]*)/i,/^(?:\s*[^:\n\s\-]+)/i,/^(?:\s*:[^:\n;]+)/i,/^(?:[\s\S]*?\n\s*end note\b)/i,/^(?:stateDiagram\s+)/i,/^(?:stateDiagram-v2\s+)/i,/^(?:hide empty description\b)/i,/^(?:\[\*\])/i,/^(?:[^:\n\s\-\{]+)/i,/^(?:\s*:(?:[^:\n;]|:[^:\n;])+)/i,/^(?:-->)/i,/^(?:--)/i,/^(?::::)/i,/^(?:$)/i,/^(?:.)/i],conditions:{LINE:{rules:[10,11,12],inclusive:!1},struct:{rules:[10,11,12,23,27,30,36,43,44,45,46,56,57,58,72,73,74,75,76,77],inclusive:!1},FLOATING_NOTE_ID:{rules:[65],inclusive:!1},FLOATING_NOTE:{rules:[62,63,64],inclusive:!1},NOTE_TEXT:{rules:[67,68],inclusive:!1},NOTE_ID:{rules:[66],inclusive:!1},NOTE:{rules:[59,60,61],inclusive:!1},STYLEDEF_STYLEOPTS:{rules:[],inclusive:!1},STYLEDEF_STYLES:{rules:[32],inclusive:!1},STYLE_IDS:{rules:[],inclusive:!1},STYLE:{rules:[31],inclusive:!1},CLASS_STYLE:{rules:[29],inclusive:!1},CLASS:{rules:[28],inclusive:!1},CLASSDEFID:{rules:[26],inclusive:!1},CLASSDEF:{rules:[24,25],inclusive:!1},acc_descr_multiline:{rules:[21,22],inclusive:!1},acc_descr:{rules:[19],inclusive:!1},acc_title:{rules:[17],inclusive:!1},SCALE:{rules:[14,15,34,35],inclusive:!1},ALIAS:{rules:[],inclusive:!1},STATE_ID:{rules:[49],inclusive:!1},STATE_STRING:{rules:[50,51],inclusive:!1},FORK_STATE:{rules:[],inclusive:!1},STATE:{rules:[10,11,12,37,38,39,40,41,42,47,48,52,53,54,55],inclusive:!1},ID:{rules:[10,11,12],inclusive:!1},INITIAL:{rules:[0,1,2,3,4,5,6,7,8,9,11,12,13,16,18,20,23,27,30,33,36,55,58,69,70,71,72,73,74,75,77,78,79],inclusive:!0}}};return V})();yt.lexer=Zt;function ht(){this.yy={}}return u(ht,"Parser"),ht.prototype=yt,yt.Parser=ht,new ht})();Ct.parser=Ct;var _e=Ct,be="TB",Gt="TB",Rt="dir",q="state",J="root",At="relation",ke="classDef",ve="style",De="applyClass",st="default",Yt="divider",Vt="fill:none",Mt="fill: #333",Wt="c",jt="markdown",Ut="normal",vt="rect",Dt="rectWithTitle",Ce="stateStart",Ae="stateEnd",xt="divider",Ot="roundedWithTitle",xe="note",Le="noteGroup",it="statediagram",we="state",Ie=`${it}-${we}`,Ht="transition",Ne="note",Re="note-edge",Oe=`${Ht} ${Re}`,$e=`${it}-${Ne}`,Fe="cluster",Pe=`${it}-${Fe}`,Be="cluster-alt",Ge=`${it}-${Be}`,zt="parent",Kt="note",Ye="state",Lt="----",Ve=`${Lt}${Kt}`,$t=`${Lt}${zt}`,St=new Map,M=0,Xt=0,Z=new Map,Me=u((t,e,n,s)=>{if(t===xt&&n?.id!==void 0&&Z.has(n.id)){const p=Z.get(n.id);return Z.set(e,p),p}const l=Xt++,c=s?void 0:l;return Z.set(e,c),c},"colorSlotFor");function gt(t="",e=0,n="",s=Lt){const l=n!==null&&n.length>0?`${s}${n}`:"";return`${Ye}-${t}${l}-${e}`}u(gt,"stateDomId");var We=u((t,e,n,s,l,c,p,S)=>{_.trace("items",e),e.forEach(a=>{switch(a.stmt){case q:et(t,a,n,s,l,c,p,S);break;case st:et(t,a,n,s,l,c,p,S);break;case At:{et(t,a.state1,n,s,l,c,p,S),et(t,a.state2,n,s,l,c,p,S);const m=p==="neo",T={id:"edge"+M,start:a.state1.id,end:a.state2.id,arrowhead:"normal",arrowTypeEnd:m?"arrow_barb_neo":"arrow_barb",style:Vt,labelStyle:"",label:U.sanitizeText(a.description??"",$()),arrowheadStyle:Mt,labelpos:Wt,labelType:jt,thickness:Ut,classes:Ht,look:p};l.push(T),M++}break}})},"setupDoc"),Ft=u((t,e=Gt)=>{let n=e;if(t.doc)for(const s of t.doc)s.stmt==="dir"&&(n=s.value);return n},"getDir");function tt(t,e,n){if(!e.id||e.id==="</join></fork>"||e.id==="</choice>")return;e.cssClasses&&(Array.isArray(e.cssCompiledStyles)||(e.cssCompiledStyles=[]),e.cssClasses.split(" ").forEach(l=>{const c=n.get(l);c&&(e.cssCompiledStyles=[...e.cssCompiledStyles??[],...c.styles])}));const s=t.find(l=>l.id===e.id);s?Object.assign(s,e):t.push(e)}u(tt,"insertOrUpdateNode");function Jt(t){return t?.classes?.join(" ")??""}u(Jt,"getClassesFromDbInfo");function qt(t){return t?.styles??[]}u(qt,"getStylesFromDbInfo");var et=u((t,e,n,s,l,c,p,S)=>{const a=e.id,m=n.get(a),T=Jt(m),x=qt(m),L=$(),R=T.trim()!==""||x.length>0;if(_.info("dataFetcher parsedItem",e,m,x),a!=="root"){let v=vt;e.start===!0?v=Ce:e.start===!1&&(v=Ae),e.type!==st&&(v=e.type),St.get(a)||St.set(a,{id:a,shape:v,description:U.sanitizeText(a,L),cssClasses:`${T} ${Ie}`,cssStyles:x});const f=St.get(a);e.description&&(Array.isArray(f.description)?(f.shape=Dt,f.description.push(e.description)):f.description?.length&&f.description.length>0?(f.shape=Dt,f.description===a?f.description=[e.description]:f.description=[f.description,e.description]):(f.shape=vt,f.description=e.description),f.description=U.sanitizeTextOrArray(f.description,L)),f.description?.length===1&&f.shape===Dt&&(f.type==="group"?f.shape=Ot:f.shape=vt),!f.type&&e.doc&&(_.info("Setting cluster for XCX",a,Ft(e)),f.type="group",f.isGroup=!0,f.dir=Ft(e),f.shape=e.type===Yt?xt:Ot,f.colorIndex=Me(f.shape,a,t,R),f.cssClasses=`${f.cssClasses} ${Pe} ${c?Ge:""}`);const w={labelStyle:"",shape:f.shape,label:f.description,cssClasses:f.cssClasses,cssCompiledStyles:[],cssStyles:f.cssStyles,id:a,dir:f.dir,domId:gt(a,M),type:f.type,isGroup:f.type==="group",colorIndex:f.colorIndex,padding:8,rx:10,ry:10,look:p,labelType:"markdown"};if(w.shape===xt&&(w.label=""),t&&t.id!=="root"&&(_.trace("Setting node ",a," to be child of its parent ",t.id),w.parentId=t.id),w.centerLabel=!0,e.note){const D={labelStyle:"",shape:xe,label:e.note.text,labelType:"markdown",cssClasses:$e,cssStyles:[],cssCompiledStyles:[],id:a+Ve+"-"+M,domId:gt(a,M,Kt),type:"node",isGroup:!1,padding:L.flowchart?.padding,look:p,position:e.note.position},F=a+$t,I={labelStyle:"",shape:Le,label:e.note.text,cssClasses:f.cssClasses,cssStyles:[],id:a+$t,domId:gt(a,M,zt),type:"group",isGroup:!0,padding:16,look:p,position:e.note.position};M++,I.id=F,D.parentId=F,tt(s,I,S),tt(s,D,S),tt(s,w,S);let P=a,k=D.id;e.note.position==="left of"&&(P=D.id,k=a),l.push({id:P+"-"+k,start:P,end:k,arrowhead:"none",arrowTypeEnd:"",style:Vt,labelStyle:"",classes:Oe,pattern:"dashed",arrowheadStyle:Mt,labelpos:Wt,labelType:jt,thickness:Ut,look:p})}else tt(s,w,S)}e.doc&&(_.trace("Adding nodes children "),We(e,e.doc,n,s,l,!c,p,S))},"dataFetcher"),je=u(()=>{St.clear(),M=0,Xt=0,Z.clear()},"reset"),Qt=u((t,e=Gt)=>{if(!t.doc)return e;let n=e;for(const s of t.doc)s.stmt==="dir"&&(n=s.value);return n},"getDir"),Ue=u(function(t,e){return e.db.getClasses()},"getClasses"),He=u(async function(t,e,n,s){_.info("REF0:"),_.info("Drawing state diagram (v2)",e);const{securityLevel:l,state:c,layout:p}=$();s.db.extract(s.db.getRootDocV2());const S=s.db.getData(),a=se(e,l);S.type=s.type,S.layoutAlgorithm=re(p),S.nodeSpacing=c?.nodeSpacing||50,S.rankSpacing=c?.rankSpacing||50,$().look==="neo"?S.markers=["barbNeo"]:S.markers=["barb"],S.diagramId=e,await ae(S,a);const T=8;try{(typeof s.db.getLinks=="function"?s.db.getLinks():new Map).forEach((L,R)=>{const v=typeof R=="string"?R:typeof R?.id=="string"?R.id:"",f=S.nodes.find(k=>k.id===v);if(!v){_.warn("⚠️ Invalid or missing stateId from key:",JSON.stringify(R));return}const w=a.node()?.querySelectorAll("g.node, g.rough-node");let D;if(w?.forEach(k=>{const H=k.textContent?.trim();(k.id===f?.domId||H===v)&&(D=k)}),!D){_.warn("⚠️ Could not find node matching text:",v);return}const F=D.parentNode;if(!F){_.warn("⚠️ Node has no parent, cannot wrap:",v);return}const I=document.createElementNS("http://www.w3.org/2000/svg","a"),P=L.url.replace(/^"+|"+$/g,"");if(I.setAttributeNS("http://www.w3.org/1999/xlink","xlink:href",P),I.setAttribute("target","_blank"),L.tooltip){const k=L.tooltip.replace(/^"+|"+$/g,"");I.setAttribute("title",k),D.setAttribute("title",k)}F.replaceChild(I,D),I.appendChild(D),_.info("🔗 Wrapped node in <a> tag for:",v,L.url)})}catch(x){_.error("❌ Error injecting clickable links:",x)}ne.insertTitle(a,"statediagramTitleText",c?.titleTopMargin??25,s.db.getDiagramTitle()),ie(a,T,it,c?.useMaxWidth??!0)},"draw"),ze={getClasses:Ue,draw:He,getDir:Qt},C={START_NODE:"[*]",START_TYPE:"start",END_NODE:"[*]",END_TYPE:"end",COLOR_KEYWORD:"color",FILL_KEYWORD:"fill",BG_FILL:"bgFill",STYLECLASS_SEP:","},Pt=u(()=>new Map,"newClassesList"),Bt=u(()=>({relations:[],states:new Map,documents:{}}),"newDoc"),pt=u(t=>JSON.parse(JSON.stringify(t)),"clone"),Ke=class{constructor(t){this.version=t,this.nodes=[],this.edges=[],this.rootDoc=[],this.classes=Pt(),this.documents={root:Bt()},this.currentDocument=this.documents.root,this.startEndCount=0,this.dividerCnt=0,this.links=new Map,this.funs=[],this.getAccTitle=oe,this.setAccTitle=le,this.getAccDescription=ce,this.setAccDescription=he,this.setDiagramTitle=de,this.getDiagramTitle=ue,this.clear(),this.setRootDoc=this.setRootDoc.bind(this),this.getDividerId=this.getDividerId.bind(this),this.setDirection=this.setDirection.bind(this),this.trimColon=this.trimColon.bind(this),this.bindFunctions=this.bindFunctions.bind(this)}static{u(this,"StateDB")}static{this.relationType={AGGREGATION:0,EXTENSION:1,COMPOSITION:2,DEPENDENCY:3}}extract(t){this.clear(!0);for(const s of Array.isArray(t)?t:t.doc)switch(s.stmt){case q:this.addState(s.id.trim(),s.type,s.doc,s.description,s.note);break;case At:this.addRelation(s.state1,s.state2,s.description);break;case ke:this.addStyleClass(s.id.trim(),s.classes);break;case ve:this.handleStyleDef(s);break;case De:this.setCssClass(s.id.trim(),s.styleClass);break;case"click":this.addLink(s.id,s.url,s.tooltip);break}const e=this.getStates(),n=$();je(),et(void 0,this.getRootDocV2(),e,this.nodes,this.edges,!0,n.look,this.classes);for(const s of this.nodes)if(Array.isArray(s.label)){if(s.description=s.label.slice(1),s.isGroup&&s.description.length>0)throw new Error(`Group nodes can only have label. Remove the additional description for node [${s.id}]`);s.label=s.label[0]}}handleStyleDef(t){const e=t.id.trim().split(","),n=t.styleClass.split(",");for(const s of e){let l=this.getState(s);if(!l){const c=s.trim();this.addState(c),l=this.getState(c)}l&&(l.styles=n.map(c=>c.replace(/;/g,"")?.trim()))}}setRootDoc(t){_.info("Setting root doc",t),this.rootDoc=t,this.version===1?this.extract(t):this.extract(this.getRootDocV2())}docTranslator(t,e,n){if(e.stmt===At){this.docTranslator(t,e.state1,!0),this.docTranslator(t,e.state2,!1);return}if(e.stmt===q&&(e.id===C.START_NODE?(e.id=t.id+(n?"_start":"_end"),e.start=n):e.id=e.id.trim()),e.stmt!==J&&e.stmt!==q||!e.doc)return;const s=[];let l=[];for(const c of e.doc)if(c.type===Yt){const p=pt(c);p.doc=pt(l),s.push(p),l=[]}else l.push(c);if(s.length>0&&l.length>0){const c={stmt:q,id:fe(),type:"divider",doc:pt(l)};s.push(pt(c)),e.doc=s}e.doc.forEach(c=>this.docTranslator(e,c,!0))}getRootDocV2(){return this.docTranslator({id:J,stmt:J},{id:J,stmt:J,doc:this.rootDoc},!0),{id:J,doc:this.rootDoc}}addState(t,e=st,n=void 0,s=void 0,l=void 0,c=void 0,p=void 0,S=void 0){const a=t?.trim();if(!this.currentDocument.states.has(a))_.info("Adding state ",a,s),this.currentDocument.states.set(a,{stmt:q,id:a,descriptions:[],type:e,doc:n,note:l,classes:[],styles:[],textStyles:[]});else{const m=this.currentDocument.states.get(a);if(!m)throw new Error(`State not found: ${a}`);m.doc||(m.doc=n),m.type||(m.type=e)}if(s&&(_.info("Setting state description",a,s),(Array.isArray(s)?s:[s]).forEach(T=>this.addDescription(a,T.trim()))),l){const m=this.currentDocument.states.get(a);if(!m)throw new Error(`State not found: ${a}`);m.note=l,m.note.text=U.sanitizeText(m.note.text,$())}c&&(_.info("Setting state classes",a,c),(Array.isArray(c)?c:[c]).forEach(T=>this.setCssClass(a,T.trim()))),p&&(_.info("Setting state styles",a,p),(Array.isArray(p)?p:[p]).forEach(T=>this.setStyle(a,T.trim()))),S&&(_.info("Setting state styles",a,p),(Array.isArray(S)?S:[S]).forEach(T=>this.setTextStyle(a,T.trim())))}clear(t){this.nodes=[],this.edges=[],this.funs=[this.setupToolTips.bind(this)],this.documents={root:Bt()},this.currentDocument=this.documents.root,this.startEndCount=0,this.classes=Pt(),t||(this.links=new Map,pe())}getState(t){return this.currentDocument.states.get(t)}getStates(){return this.currentDocument.states}logDocuments(){_.info("Documents = ",this.documents)}getRelations(){return this.currentDocument.relations}addLink(t,e,n){this.links.set(t,{url:e,tooltip:n}),_.warn("Adding link",t,e,n)}getLinks(){return this.links}startIdIfNeeded(t=""){return t===C.START_NODE?(this.startEndCount++,`${C.START_TYPE}${this.startEndCount}`):t}startTypeIfNeeded(t="",e=st){return t===C.START_NODE?C.START_TYPE:e}endIdIfNeeded(t=""){return t===C.END_NODE?(this.startEndCount++,`${C.END_TYPE}${this.startEndCount}`):t}endTypeIfNeeded(t="",e=st){return t===C.END_NODE?C.END_TYPE:e}addRelationObjs(t,e,n=""){const s=this.startIdIfNeeded(t.id.trim()),l=this.startTypeIfNeeded(t.id.trim(),t.type),c=this.startIdIfNeeded(e.id.trim()),p=this.startTypeIfNeeded(e.id.trim(),e.type);this.addState(s,l,t.doc,t.description,t.note,t.classes,t.styles,t.textStyles),this.addState(c,p,e.doc,e.description,e.note,e.classes,e.styles,e.textStyles),this.currentDocument.relations.push({id1:s,id2:c,relationTitle:U.sanitizeText(n,$())})}addRelation(t,e,n){if(typeof t=="object"&&typeof e=="object")this.addRelationObjs(t,e,n);else if(typeof t=="string"&&typeof e=="string"){const s=this.startIdIfNeeded(t.trim()),l=this.startTypeIfNeeded(t),c=this.endIdIfNeeded(e.trim()),p=this.endTypeIfNeeded(e);this.addState(s,l),this.addState(c,p),this.currentDocument.relations.push({id1:s,id2:c,relationTitle:n?U.sanitizeText(n,$()):void 0})}}addDescription(t,e){const n=this.currentDocument.states.get(t),s=e.startsWith(":")?e.replace(":","").trim():e;n?.descriptions?.push(U.sanitizeText(s,$()))}cleanupLabel(t){return t.startsWith(":")?t.slice(2).trim():t.trim()}getDividerId(){return this.dividerCnt++,`divider-id-${this.dividerCnt}`}addStyleClass(t,e=""){this.classes.has(t)||this.classes.set(t,{id:t,styles:[],textStyles:[]});const n=this.classes.get(t);e&&n&&e.split(C.STYLECLASS_SEP).forEach(s=>{const l=s.replace(/([^;]*);/,"$1").trim();if(RegExp(C.COLOR_KEYWORD).exec(s)){const p=l.replace(C.FILL_KEYWORD,C.BG_FILL).replace(C.COLOR_KEYWORD,C.FILL_KEYWORD);n.textStyles.push(p)}n.styles.push(l)})}getClasses(){return this.classes}setupToolTips(t){const e=Ee();kt(t).select("svg").selectAll("g.node, g.rough-node").on("mouseover",l=>{const c=kt(l.currentTarget),p=c.attr("title");if(p===null)return;const S=l.currentTarget?.getBoundingClientRect();e.transition().duration(200).style("opacity",".9"),e.style("left",window.scrollX+S.left+(S.right-S.left)/2+"px").style("top",window.scrollY+S.bottom+"px"),e.html(Se.sanitize(p)),c.classed("hover",!0)}).on("mouseout",l=>{e.transition().duration(500).style("opacity",0),kt(l.currentTarget).classed("hover",!1)})}setCssClass(t,e){t.split(",").forEach(n=>{let s=this.getState(n);if(!s){const l=n.trim();this.addState(l),s=this.getState(l)}s?.classes?.push(e)})}setStyle(t,e){this.getState(t)?.styles?.push(e)}setTextStyle(t,e){this.getState(t)?.textStyles?.push(e)}bindFunctions(t){this.funs.forEach(e=>{e(t)})}getDirectionStatement(){return this.rootDoc.find(t=>t.stmt===Rt)}getDirection(){return this.getDirectionStatement()?.value??be}setDirection(t){const e=this.getDirectionStatement();e?e.value=t:this.rootDoc.unshift({stmt:Rt,value:t})}trimColon(t){return t.startsWith(":")?t.slice(1).trim():t.trim()}getData(){const t=$();for(const e of this.nodes)e.wrappingWidth??=t.state?.wrappingWidth,e.isGroup||(e.minWidth??=t.state?.minNodeWidth);return{nodes:this.nodes,edges:this.edges,other:{},config:t,direction:Qt(this.getRootDocV2())}}getConfig(){return $().state}},Xe=u(t=>{const{theme:e,bkgColorArray:n,borderColorArray:s}=t;if(!ge(e,s))return"";const l=ye(t.look),c=me(n);let p="";for(let S=0;S<Te(s);S++){const a=s[S],m=c?`fill: ${n[S%n.length]};`:"",T=`[data-look="${l}"][data-color-id="color-${S}"]`;p+=`

    /* The title strip: \`rect.outer\` spans the whole composite and \`rect.inner\` covers
       the body, so what stays visible of \`outer\` is the band behind the label. */
    ${T}.statediagram-cluster rect.outer {
      stroke: ${a};
      ${m}
    }

    ${T}.statediagram-cluster rect.inner {
      stroke: ${a};
    }

    /* Concurrency regions. Siblings of one composite share a slot, so a divided composite
       reads as one thing split into parts rather than as several composites. */
    ${T}.statediagram-cluster rect.divider {
      stroke: ${a};
      ${m}
    }

    /* handDrawn draws the same container as roughjs shapes rather than plain rects, so it
       needs its own rules. \`roundedWithTitle\` and \`divider\` name those groups \`outer\`,
       \`inner\` and \`divider\` to match the classic branch, which is what lets these
       discriminate -- a bare \`.statediagram-cluster path\` rule reached the body as well and
       tinted the whole composite, losing \`compositeBackground\` and diverging from what
       classic and neo do.

       roughjs emits two paths per shape and marks them: the filled shape carries
       \`stroke="none"\` and the sketched outline carries \`fill="none"\`. Splitting on that is
       what keeps \`fill\` off the outline -- a rough outline is open squiggles, not a closed
       region, so filling it produces smears -- and keeps \`stroke\` off the fill shape, which
       would otherwise gain an edge it was drawn without. */
    ${T}.statediagram-cluster .outer path[stroke='none'] {
      ${m}
    }

    ${T}.statediagram-cluster .outer path[fill='none'] {
      stroke: ${a};
    }

    /* No \`.inner\` rule on purpose. The body shape is left entirely alone under handDrawn,
       where a rect's \`inner\` counterpart cannot be recoloured safely: roughjs draws a
       hachure fill as *stroked* lines, so its fill paths carry \`fill="none"\` exactly like
       the outline and no selector separates them. An \`.inner\` stroke rule therefore
       repainted the hatching of every alt composite in the palette colour instead of
       leaving it on \`altBackground\`. The container still reads as palette-coloured: the
       \`outer\` shape spans the whole composite, so its outline already frames the body. */

    /* Regions split the same way, which is why \`divider\` fills solid rather than taking
       roughjs's default hachure -- see the note on that call. Hatched, both of its paths
       carried \`fill="none"\` and these two rules degenerated: the tint matched nothing and
       the border rule repainted the hatching. */
    ${T}.statediagram-cluster .divider path[stroke='none'] {
      ${m}
    }

    ${T}.statediagram-cluster .divider path[fill='none'] {
      stroke: ${a};
    }
    `}return p},"genColor"),Je=u(t=>`
${Xe(t)}
defs [id$="-barbEnd"] {
    fill: ${t.transitionColor};
    stroke: ${t.transitionColor};
  }
g.stateGroup text {
  fill: ${t.nodeBorder};
  stroke: none;
  font-size: 10px;
}
g.stateGroup text {
  fill: ${t.textColor};
  stroke: none;
  font-size: 10px;

}
g.stateGroup .state-title {
  font-weight: bolder;
  fill: ${t.stateLabelColor};
}

g.stateGroup rect {
  fill: ${t.mainBkg};
  stroke: ${t.nodeBorder};
}

g.stateGroup line {
  stroke: ${t.lineColor};
  stroke-width: ${t.strokeWidth||1};
}

.transition {
  stroke: ${t.transitionColor};
  stroke-width: ${t.strokeWidth||1};
  fill: none;
}

.stateGroup .composit {
  fill: ${t.background};
  border-bottom: 1px
}

.stateGroup .alt-composit {
  fill: #e0e0e0;
  border-bottom: 1px
}

.state-note {
  stroke: ${t.noteBorderColor};
  fill: ${t.noteBkgColor};

  text {
    fill: ${t.noteTextColor};
    stroke: none;
    font-size: 10px;
  }
}

.stateLabel .box {
  stroke: none;
  stroke-width: 0;
  fill: ${t.mainBkg};
  opacity: 0.5;
}

.edgeLabel .label rect {
  fill: ${t.labelBackgroundColor};
  opacity: 0.5;
}
.edgeLabel {
  background-color: ${t.edgeLabelBackground};
  p {
    background-color: ${t.edgeLabelBackground};
  }
  rect {
    opacity: 0.5;
    background-color: ${t.edgeLabelBackground};
    fill: ${t.edgeLabelBackground};
  }
  text-align: center;
}
.edgeLabel .label text {
  fill: ${t.transitionLabelColor||t.tertiaryTextColor};
}
.label div .edgeLabel {
  color: ${t.transitionLabelColor||t.tertiaryTextColor};
}

.stateLabel text {
  fill: ${t.stateLabelColor};
  font-size: 10px;
  font-weight: bold;
}

.node circle.state-start {
  fill: ${t.specialStateColor};
  stroke: ${t.specialStateColor};
}

.node .fork-join {
  fill: ${t.specialStateColor};
  stroke: ${t.specialStateColor};
}

.node circle.state-end {
  fill: ${t.innerEndBackground};
  stroke: ${t.background};
  stroke-width: 1.5
}
.end-state-inner {
  fill: ${t.compositeBackground||t.background};
  // stroke: ${t.background};
  stroke-width: 1.5
}

.node rect {
  fill: ${t.stateBkg||t.mainBkg};
  stroke: ${t.stateBorder||t.nodeBorder};
  stroke-width: ${t.strokeWidth||1}px;
}
.node polygon {
  fill: ${t.mainBkg};
  stroke: ${t.stateBorder||t.nodeBorder};;
  stroke-width: ${t.strokeWidth||1}px;
}
[id$="-barbEnd"] {
  fill: ${t.lineColor};
}

.statediagram-cluster rect {
  fill: ${t.compositeTitleBackground};
  stroke: ${t.stateBorder||t.nodeBorder};
  stroke-width: ${t.strokeWidth||1}px;
}

.cluster-label, .nodeLabel {
  color: ${t.stateLabelColor};
  // line-height: 1;
}

.statediagram-cluster rect.outer {
  rx: 5px;
  ry: 5px;
}
.statediagram-state .divider {
  stroke: ${t.stateBorder||t.nodeBorder};
}

.statediagram-state .title-state {
  rx: 5px;
  ry: 5px;
}
.statediagram-cluster.statediagram-cluster .inner {
  fill: ${t.compositeBackground||t.background};
}
.statediagram-cluster.statediagram-cluster-alt .inner {
  fill: ${t.altBackground?t.altBackground:"#efefef"};
}

.statediagram-cluster .inner {
  rx:0;
  ry:0;
}

.statediagram-state rect.basic {
  rx: 5px;
  ry: 5px;
}
.statediagram-state rect.divider {
  stroke-dasharray: 10,10;
  fill: ${t.altBackground?t.altBackground:"#efefef"};
}

.note-edge {
  stroke-dasharray: 5;
}

.statediagram-note rect {
  fill: ${t.noteBkgColor};
  stroke: ${t.noteBorderColor};
  stroke-width: 1px;
  rx: 0;
  ry: 0;
}
.statediagram-note rect {
  fill: ${t.noteBkgColor};
  stroke: ${t.noteBorderColor};
  stroke-width: 1px;
  rx: 0;
  ry: 0;
}

.statediagram-note text {
  fill: ${t.noteTextColor};
}

.statediagram-note .nodeLabel {
  color: ${t.noteTextColor};
}
.statediagram .edgeLabel {
  color: red; // ${t.noteTextColor};
}

[id$="-dependencyStart"], [id$="-dependencyEnd"] {
  fill: ${t.lineColor};
  stroke: ${t.lineColor};
  stroke-width: ${t.strokeWidth||1};
}

.statediagramTitleText {
  text-anchor: middle;
  font-size: 18px;
  fill: ${t.textColor};
}

[data-look="neo"].statediagram-cluster rect {
  fill: ${t.mainBkg};
  stroke: ${t.useGradient?"url("+t.svgId+"-gradient)":t.stateBorder||t.nodeBorder};
  stroke-width: ${t.strokeWidth??1};
}
[data-look="neo"].statediagram-cluster rect.outer {
  rx: ${t.radius}px;
  ry: ${t.radius}px;
  filter: ${t.dropShadow?t.dropShadow.replace("url(#drop-shadow)",`url(${t.svgId}-drop-shadow)`):"none"}
}
`,"getStyles"),qe=Je,us={parser:_e,get db(){return new Ke(2)},renderer:ze,styles:qe,init:u(t=>{t.state||(t.state={}),t.state.arrowMarkerAbsolute=t.arrowMarkerAbsolute},"init")};export{us as diagram};
