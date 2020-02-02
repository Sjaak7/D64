var d64=(function(){
// C = chat, S = socket
var
	C,
	S,
	o,
	hidden,
	visibilityChange,
	chatInput,
	tempNick,
	chatChan="lobby",
	btcChat=true,
	startingX,
	scrollTimeout,
	version="0.0.4";

function wss(){
	try{
		S=new WebSocket("wss://d64.nl/live");
		S.onopen=()=>{
			setColor("life","green");
			if(window.location.pathname==='/')
				send(JSON.stringify({mod:"chat",rq:"init"}));
			if(chatInput!==null)
				initChatInput();
		};
		S.onerror=(e)=>{
			l(e);
		};
		S.onmessage=(m)=>{
			setColor("life","#ff0");
			validate(m.data);
			l(m.data)
		};
		S.onclose=()=>{
			setColor("life","red");
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
	setColor("life","blue");
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
	else if(typeof(o.acc_nick)!=="undefined"&&o.acc_nick===tempNick){
		setCookie("chat",tempNick,100);
		changeToInput();
	}else if(typeof(o.err)!=="undefined"){
		if(o.err==="dup_nick"){
			chatPlaceholder("Deze nicknaam bestaat al, kies een andere..");
			removeCookie();
		}else if(o.err==="ill_nick"){
			chatPlaceholder("Nicknaam bevat illegale karakters");
			removeCookie();
		}else if(o.err==="dup_chan"){
			chatStack({"n":"system","m":"Gekozen kanaal bestaat al"});
		}else if(o.err==="ill_chan")
			chatStack({"n":"system","m":"Kanaalnaam mag alleen letters bevatten, minimaal 3 karakters, maximaal 9"});
	}else if(typeof(o.acc_chan)!=="undefined"){
		//alert('lets create a channel!');
	}
}
function removeCookie(){
	document.cookie="chat=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
}
function chatPlaceholder(p){
	chatInput.placeholder=p;
	chatInput.value="";
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
function validate(d){
	try{
		o=JSON.parse(d);
		if(chatInput&&typeof(o.mod)!=="undefined"&&o.mod==="btc"&&btcChat===true){
			chatStack({"n":"btc-bot","m":"&euro; "+o.btc_euro});
			setColor("life","green");
		}else if(chatInput&&typeof(o.mod)!=="undefined"&&o.mod==="chat"){
			chatParser();
			setColor("life","green");
		}
	}catch(e){
		l(e);
	}
}
function scrollDown(){
	content=document.getElementById("cFrame");
	document.getElementById("p1").scrollTo(0,content.scrollHeight);
}
function setColor(i,c){
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
function setCookie(cn,cv,exd){
	var d=new Date(),exp;
	d.setTime(d.getTime()+(exd*24*60*60*1000));
	exp="expires="+d.toUTCString();
	document.cookie=cn+"="+cv+";"+exp+";path=/;secure";
}
function getCookie(cname){
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
function initChat(){
	initChatInput();
	chatInput.addEventListener("keyup",(e)=>{
	if(e.keyCode===13&&chatInput.value!==""){
		chatInput.value=removeLinebreaks(chatInput.value);
		// Set the nickname
		if(getCookie("chat")===""){
			if(checkNick(chatInput.value,"init"))
				changeNick(chatInput.value);
			}else if(checkNick(getCookie("chat"),"init")&&chatInput.value.trim().length>0){
				if(!chatCommands()){
					send(JSON.stringify({mod:"chat",chan:chatChan,msg:{n:getCookie("chat"),m:chatInput.value}}));
					chatInput.value=""
				}
			}
		}
	});
	initTouch();
}
function changeToInput(){
	chatInput.setAttribute("maxlength",128);
	chatPlaceholder("Hoi "+getCookie("chat")+", type hier je bericht of type /help voor help");
}
function initChatInput(){
	if(getCookie("chat")==="")
		chatPlaceholder("Type eerst je nicknaam..");
	else if(checkNick(getCookie("chat"),"init"))
		changeToInput();
}
function checkNick(n,type){
	if(n.match(/^([a-zA-Z0-9_-]{1,9})$/g)===null){
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
			chatInput.value="";
		}else{
			chatStack({"n":"system","m":m});
			chatInput.value="";
		}
	}
}
function checkChannel(c){
	if(c.match(/^[A-Za-z]{3,9}$/g)){
		cI.value="";
		changeToInput();
		return true;
	}else{
		chatStack({"n":"system","m":"Geen geldige kanaal naam.."});
		return false;
	}
}
function changeNick(n){
	tempNick=n;
        send(JSON.stringify({mod:"chat",rq:"nick",nick:tempNick}));
        chatPlaceholder("Nicknaam controleren..");
}
function chatConnStatus(){
	if(chatInput!==null){
		if(S.readyState===S.CLOSED)
			chatPlaceholder("Verbinding verbroken :( ik probeer opnieuw..");
		else if(S.readyState===S.OPEN)
			initChatInput();
	}
}
function chatCommands(){
	if(chatInput.value.match(/^\/nick\s/)){
		var rqNick=chatInput.value.replace(/^\/nick\s/,"");
		if(checkNick(rqNick,"command"))
			changeNick(rqNick);
		return true;
	}else if(chatInput.value.match(/^\/help$/)){
		var help=[
			"[D64] Help",
			" ",
			"De volgende commando's zijn beschikbaar:",
			" ",
			"/nick naam",
			"/channel kanaal",
			"/help"
		];
		for(var i in help)
			chatStack({"n":"system","m":help[i]});
		chatInput.value="";
		return true;
	}else if(chatInput.value.match(/^\/channel\s/)){
		var rqChan=chatInput.value.replace(/^\/channel\s/,"");
		if(checkChannel(rqChan))
			send(JSON.stringify({mod:"chat",rq:"chan",chan:rqChan}));
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
	navOffset,maxPanelHeight;

	checkSetHeight();
	addListeners();

	function addListeners(){
		p1.addEventListener("touchstart",start);
		p1.addEventListener("touchmove",move);
		p1.addEventListener("touchend",end);

		window.addEventListener('keyup',keyboard);
	}
	function removeListeners(){
		p1.removeEventListener("touchstart",start);
		p1.removeEventListener("touchmove",move);
		p1.removeEventListener("touchend",end);

		window.removeEventListener("keyup",keyboard);
	}
	function checkSetHeight(){
		navOffset=document.getElementById("nav").offsetHeight,
		maxPanelHeight=window.innerHeight-document.getElementById("footer").offsetHeight-navOffset+"px";

		p1.style.height=maxPanelHeight;
		p1.style.top=navOffset+"px";
		p2.style.height=maxPanelHeight;
		p2.style.top=navOffset+"px";
	}
	function keyboard(e){
                if(e.key==='ArrowLeft'){
			clearTimeout(scrollTimeout);

			endMove();
                }
	}
	function start(e){
		clearTimeout(scrollTimeout);
		startingX=e.touches[0].clientX;
	}
	function move(e){
                var touch=e.touches[0],change=startingX-touch.clientX;

                if(change<0){
			swipeRight(Math.abs(change));
		}else{
			swipeLeft(change);
		}
	}
	function swipeRight(change){
		p1.style.left="+"+change+"px";
		p2.style.display="block";
		p2.style.left=(-screen.width+change)+"px";
	}
	function swipeLeft(change){
		p1.style.left="-"+change+"px";
		p2.style.display="block";
		p2.style.left=(screen.width-change)+"px";
	}
	function end(e){
                var change=startingX-e.changedTouches[0].clientX,threshold=screen.width/3;
                if(Math.abs(change)<threshold){
                        p1.style.left=0;

                        p2.style.left="100%";
                        p2.style.display="none";
                }else endMove();
	}
	function endMove(change){
	//	p1.style.transition="all .3s";
	//	p2.style.transition="all .3s";
		if(change>0)
			p1.style.left="-100%";
		else p1.style.left=screen.width;

		p1.classList.add("hiddenPage");
		p1.classList.remove("visiblePage");
		p1.removeAttribute("style");
		p2.classList.add("visiblePage");
		p2.classList.remove("hiddenPage");
		p2.removeAttribute("style");

		scrollTimer();
		removeListeners();
		initTouch();
	}
	function scrollTimer(){
		scrollTimeout=setTimeout(()=>{
			p2.scrollTo(0,document.getElementById(p2.id).scrollHeight);
		},700)
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
		if(chatInput) initTouch();
		else height();
		scrollDown();
	});
	if(document.getElementById("version"))
		document.getElementById("version").innerHTML=version;
	if(document.getElementById("btc")){
		document.getElementById("btc").addEventListener("change",()=>{
			if(this.checked)
				btcChat=true;
			else btcChat=false;
		});
	}
	chatInput=document.getElementById("cI");
	if(chatInput) initChat();
	else height();
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
