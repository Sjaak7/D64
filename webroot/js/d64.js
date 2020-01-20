var d64=(function(){
// C = chat, S = socket, cI = chat input, tN = temp nick
var C,S,o,hidden,visibilityChange,cI,tN,btcChat=true,startingX,scrollTimeout,apppage=false;
function wss(){
	try{
		S=new WebSocket("wss://d64.nl/live");
		S.onopen=()=>{
			sC("life","green");
			if(window.location.pathname==='/')
				send(JSON.stringify({mod:"chat",rq:"init"}));
			if(cI!==null)
				initChat();
		};
		S.onerror=(e)=>{
			l(e);
		};
		S.onmessage=(m)=>{
			sC("life","#ff0");
			v(m.data);
			l(m.data)
		};
		S.onclose=()=>{
			sC("life","red");
			chatConnStatus();
			if(!document[hidden])
				setTimeout(wss,1000);
		};
	}catch(e){
		l(e)
	}
}
function l(m){
	console.log(m)
}
function send(m){
	sC("life","blue");
	S.send(m)
}
function chatParser(){
	if(typeof(o.chat)!=="undefined"){
		if(typeof(C)==="undefined"||o.chat.length>1){
			C=o;
			chatPrint();
		}else chatStack(o.chat[0]);
	}
	if(typeof(o.nicks)!=="undefined")
		onlineNicks(o.nicks);
	else if(typeof(o.qjb)!=="undefined"&&o.qjb===tN){
		SC("chat",tN,100);
		changeToInput();
	}else if(typeof(o.err)!=="undefined"&&o.err==="dup_nick"){
		chatPlaceholder("Deze nicknaam bestaat al, kies een andere..");
		document.cookie = "chat=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
	}
}
function chatPlaceholder(p){
	cI.placeholder=p;
	cI.value="";
}
function onlineNicks(n){
	var output="";
	if(n!=="none"){
		for(i in n)
			output+="<span>"+n[i]["n"]+"</span>, ";
		document.getElementById("cN").innerHTML="Online chatters: "+output.substring(0,output.length-2);
	}else document.getElementById("cN").innerHTML="";
	scrollDown();
}
function chatStack(m){
	if(C.chat.length===30)
		C.chat.shift();
	C.chat.push(m);
	chatPrint();
}
function chatPrint(){
	var i,x='';
	for(i in C.chat)
		x+="<div>"+C.chat[i]["n"]+": "+C.chat[i]["m"]+"</div>";
	document.getElementById("cB").innerHTML=x;
	scrollDown();
}
// Validate
function v(d){
	try{
		o=JSON.parse(d);
		if(apppage&&typeof(o.mod)!=="undefined"&&o.mod==="btc"&&btcChat===true){
			chatStack({"n":"btc-bot","m":"&euro; "+o.btc_euro});
			sC("life","green");
		}else if(apppage&&typeof(o.mod)!=="undefined"&&o.mod==="chat"){
			chatParser();
			sC("life","green");
		}
	}catch(e){
		l(e);
	}
}
function scrollDown(){
	content=document.getElementById("cFrame");
	document.getElementById("p1").scrollTo(0,content.scrollHeight);
}
// set color
function sC(i,c){
	document.getElementById(i).style.color=c
}
// visibility
if(typeof(document.hidden)!=="undefined"){
	hidden="hidden";
	visibilityChange="visibilitychange"
}else if(typeof(document.msHidden)!=="undefined"){
	hidden="msHidden";
	visibilityChange="msvisibilitychange"
}else if(typeof(document.webkitHidden)!=="undefined"){
	hidden="webkitHidden";
	visibilityChange="webkitvisibilitychange"
}
function handleVisibilityChange(){
	if(document[hidden]){
		S.onclose=()=>{};
		S.close()
	}else wss()
}
if(typeof(document.addEventListener)!=="undefined"||hidden===undefined)
	document.addEventListener(visibilityChange,handleVisibilityChange,false);
// setCookie
function SC(cn,cv,exd){
	var d=new Date(),exp;
	d.setTime(d.getTime()+(exd*24*60*60*1000));
	exp="expires="+d.toUTCString();
	document.cookie=cn+"="+cv+";"+exp+";path=/;secure";
}
// getCookie
function gC(cname){
	var name=cname+"=",decodedCookie=decodeURIComponent(document.cookie),ca=decodedCookie.split(";"),i;
	for(i=0;i<ca.length;i++){
		var c=ca[i];
		while(c.charAt(0)==" ")
			c=c.substring(1);
		if(c.indexOf(name)==0)
			return c.substring(name.length,c.length);
	}
	return "";
}
function changeToInput(){
	cI.setAttribute("maxlength",128);
	chatPlaceholder("Hoi "+gC("chat")+", type hier je bericht of type /help voor help");
}
function initChat(){
	if(gC("chat")==="")
		chatPlaceholder("Type eerst je nicknaam..");
	else if(checkNick(gC("chat"),"init"))
		changeToInput();
}
function checkNick(n,type){
	if(n.match(/^([A-z0-9_-]{1,9})$/g)===null){
		if(checkNickLength(n,type))
			checkNickInfo("Nicknaam mag alleen letters, cijfers en _ of - bevatten",type);
		else return false;
	}else return checkNickLength(n,type);
	function checkNickLength(n,type){
		if(n.length<3){
			checkNickInfo("Nicknaam moet minimaal 3 karakters zijn",type);
			return false;
		}else if(n.length>9){
			checkNickInfo("Nicknaam mag maximaal 9 karakters zijn",type);
			return false;
		}else return true;
	}
	function checkNickInfo(m,type){
		if(type==="init"){
			chatPlaceholder(m);
			cI.value="";
		}else{
			chatStack({"n":"system","m":m});
			cI.value="";
		}
	}
}
function changeNick(n){
	tN=n;
        send(JSON.stringify({mod:"chat",rq:"nick",nick:tN}));
        chatPlaceholder("Nicknaam controleren..");
}
// connection status
function chatConnStatus(){
	if(cI!==null){
		if(S.readyState===S.CLOSED)
			chatPlaceholder("Verbinding verbroken :( ik probeer opnieuw..");
		else if(S.readyState===S.OPEN)
			initChat();
	}
}
function chatCommands(){
	if(cI.value.match(/^\/nick\s/)){
		cI.value=cI.value.replace(/^\/nick\s/,"");
		if(checkNick(cI.value,"command"))
			changeNick(cI.value);
		return true;
	}else if(cI.value.match(/^\/help$/)){
		var help=[
			"[D64] Help",
			" ",
			"De volgende commando's zijn beschikbaar:",
			" ",
			"/nick naam",
			"/help"
		];
		for(var i in help)
			chatStack({"n":"system","m":help[i]});
		cI.value="";
		return true;
	}else return false;
}
function removeLinebreaks(m){
	return m.replace(/(\r\n|\n|\r)/gm,"");
}
function initTouch(){
	var
	p1=document.getElementsByClassName("visiblePage")[0],
	p2=document.getElementsByClassName("hiddenPage")[0],
	navOffset=document.getElementById("nav").offsetHeight,
	maxPanelHeight=window.innerHeight-document.getElementById("footer").offsetHeight-navOffset+"px";

	p1.style.height=maxPanelHeight;
	p1.style.top=navOffset+"px";
        p2.style.height=maxPanelHeight;
        p2.style.top=navOffset+"px";

	p1.addEventListener("touchstart",start);
	p1.addEventListener("touchmove",move);
	p1.addEventListener("touchend",end);

	window.addEventListener('keyup',keyboard);

	function keyboard(e){
                if(e.key==='ArrowLeft'){
			window.removeEventListener("keyup",keyboard);
			clearTimeout(scrollTimeout);

                        p1.style.transition="all .3s";
                        p2.style.transition="all .3s";
                        p1.style.left="-100%";

                        p1.classList.add("hiddenPage");
                        p1.classList.remove("visiblePage");
                        p1.removeAttribute("style");
                        p2.classList.add("visiblePage");
                        p2.classList.remove("hiddenPage");
                        p2.removeAttribute("style");

			scrollTimer();

                        initTouch();
                }
	}
	function start(e){
		clearTimeout(scrollTimeout);
		startingX=e.touches[0].clientX;
	}
	function move(e){
                var touch=e.touches[0],change=startingX-touch.clientX;
                p1.style.left="-"+change+"px";

                if(change<0)
                        return;

                p1.style.left="-"+change+"px";
                p2.style.display="block";
                p2.style.left=(screen.width-change)+"px";
	}
	function end(e){
                var change=startingX-e.changedTouches[0].clientX,threshold=screen.width/3;
                if(change<threshold){
                        p1.style.left=0;

                        p2.style.left="100%";
                        p2.style.display="none";
                }else{
                        p1.style.transition="all .3s";
                        p2.style.transition="all .3s";
                        p1.style.left="-100%";

                        p1.classList.add("hiddenPage");
                        p1.classList.remove("visiblePage");
                        p1.removeAttribute("style");
                        p2.classList.add("visiblePage");
                        p2.classList.remove("hiddenPage");
                        p2.removeAttribute("style");

			p1.removeEventListener("touchstart",start);
			p1.removeEventListener("touchmove",move);
			p1.removeEventListener("touchend",end);

			scrollTimer();

			initTouch();
                }
	}
	function scrollTimer(){
		scrollTimeout=setTimeout(function(){
			p2.scrollTo(0,document.getElementById(p2.id).scrollHeight);
		},700);
	}
}
function height(){
	var
	maxPanelHeight=window.innerHeight-document.getElementById("footer").offsetHeight-document.getElementById("nav").offsetHeight+"px",
	content=document.getElementById("content");
	content.style.height=maxPanelHeight;
	content.style.top=document.getElementById("nav").offsetHeight+"px";
}
document.addEventListener("DOMContentLoaded",()=>{
	window.addEventListener("resize",()=>{
		if(!apppage)
			height();
		scrollDown();
	});
	if(document.location.pathname==='/'){
		apppage=true;
		initTouch();
	}else height();
	if(document.getElementById("btc")){
		document.getElementById("btc").addEventListener("change",()=>{
			if(this.checked)
				btcChat=true;
			else btcChat=false;
		});
	}
	cI=document.getElementById("cI");
	if(cI!==null){
		initChat();
		cI.addEventListener("keyup",(e)=>{
			if(e.keyCode===13&&cI.value!==""){
				cI.value=removeLinebreaks(cI.value);
				// Set the nickname
				if(gC("chat")===""){
					if(checkNick(cI.value,"init"))
						changeNick(cI.value);
				}else if(checkNick(gC("chat"),"init")&&cI.value.trim().length>0){
					if(!chatCommands()){
						send(JSON.stringify({mod:"chat",cB:{n:gC("chat"),m:cI.value}}));
						cI.value=""
					}
				}
			}
		})
	}
	wss();
},false);
if('serviceWorker' in navigator){
	navigator.serviceWorker.register('/service-worker.js',{scope:'/'}).then((r)=>{
		l('Registration succeeded. Scope is '+r.scope);
	}).catch((e)=>{
		l('Registration failed with '+e);
	});
}
})();
