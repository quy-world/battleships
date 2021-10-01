function arrayof(collection) {
	var array = [];
	for(var i=0; i<collection.length; i++) {
		array.push(collection.item(i))
	}
	return array;
}
const buyships = arrayof(document.getElementById('shipbox').getElementsByClassName('ship'))

function siblingNumber(thing) { 
// we pass through a reference of the thing, 
	// then we check to find the thing (using that same thing) in it's list of things
	var me
	var siblings = thing.parentElement.children
	for(var i=0; i<siblings.length; i++){
		if(siblings.item(i) == thing) {
		  me = i
		}
	}
	return me;
}


function offset(num){
	return Math.floor(num/40);
}

function sendHit(x, y){ // procedure
	//return;	
	alert(['sending hit x, y', x, y].join(';'))
	var theform = document.createElement('form')
	theform.style.display = 'none';
	theform.action = "/battleships/output.php"
	theform.method = "post"
	theform.type = "hidden"
	var mi = () => document.createElement('input')
	var fx = mi()
	var fy = mi()
	var fs = mi()
	var gid = mi()
	fx.name = 'hitx'
	fy.name = 'hity'
	fs.name = 'moisession'
	gid.name = 'game_id'
	fx.value = x
	fy.value = y
	fs.value = moisession
	gid.value = game_id
	theform.appendChild(fx)
	theform.appendChild(fy)
	theform.appendChild(fs)
	theform.appendChild(gid)
	document.body.appendChild(theform)
	theform.submit();
}


var lastx=0; var lasty=0;
//image runs when page loads
document.addEventListener('DOMContentLoaded', function(event) {
	//DIVS OUTPUT

	// NICE CSS
	 document.getElementsByClassName('ocean enemy')
		.item(0).addEventListener("click", function(e){
		if(e.target.classList.contains('hitbox')){ removeGB(); return; }
    	sendHit(lastx, lasty)
	})

	document.getElementsByClassName('ocean enemy')
		.item(0).addEventListener("mouseleave", function(e){
		document.getElementById('greenbox').remove();
		lastx=0; lasty=0;
	})

				
	document.getElementsByClassName('ocean enemy')
		.item(0).addEventListener("mousemove", function(e){
			var x=offset(e.offsetX); var y=offset(e.offsetY)
			
			var tempcond = (
				 e.target.children.item(0)===null
				?false
				:e.target.children.item(0).classList.contains('exes')
			);
			if(
				e.target.style.background==='orange'||e.target.classList.contains('yellow')||e.target.classList.contains('exes')||e.target.classList.contains('lastthree')||e.target.parentElement.classList.contains('lastthree')||tempcond
			)
			{ removeGB(); return; }
			greenHndlr(e)
		}) 
	// innefficient... qqq
	 document.getElementsByClassName('ocean enemy')
		.item(0).addEventListener("mousemove", function(e){
		//console.log('mouse x y', e.offsetX, e.offsetY)
		if(glob.clicked!==undefined){
			console.log('mouse x y', e.offsetX, e.offsetY)
		}
	}) 
		buyships.forEach(s => {
			s.style.marginLeft = ''
			s.style.marginTop = ''
			s.style.float = 'left'
		})
})

function greenHndlr(e){
	//var x; var y;
	if(document.getElementById('greenbox')!==null&&e.target.id==='greenbox'){ return; }
	lastx=offset(e.offsetX); lasty=offset(e.offsetY);
	greenbox(offset(e.offsetX), offset(e.offsetY))
}

function removeGB(){
	var gb = document.getElementById('greenbox')
	if(gb!==null){ gb.remove() };
}

function greenbox(x, y){ //return (e) => {
	// add stuff here...
	removeGB();
	if(x<0||y<0){ return; }
	var style = 'left: '+x*40+'px; top: '+y*40+'px;'
	document.getElementsByClassName('ocean enemy').item(0).innerHTML 
		+= '<div id="greenbox" style="'+style+'"></div>'
}

let glob = {
	  clicked: undefined
	, 	diffX: undefined
	, 	diffY: undefined
}
// HEREEEEEE
let me = document.getElementsByClassName('friendly').item(0)
let meTop = 70
let [mex, mey] = [getx(me), gety(me)+meTop]
function tgt(e){ return e.target }

function getx(el){  return el.getBoundingClientRect().x }
function gety(el){  return el.getBoundingClientRect().y }

function setglob(clicked, x, y, ex, ey){
	glob.clicked   = clicked
	glob.diffX		 = x
	glob.diffY		 = y
}

function unsetglob(){
	// resets 'glob' const
	glob.clicked.style.position = 'relative'
	glob.clicked.style.left = '0'
	glob.clicked.style.top = '0'
	glob = {}
}

function hasParentId(parentName, elem){
	if(elem===document.body){ return false }
	if(elem.id===parentName){ return true  }
	return hasParentId(parentName, elem.parentElement)
}
function shipheight(elem){ return Number(elem.style.height.slice(0,-2)) }
function shipwidth(elem) { return Number(elem.style.width.slice(0,-2)) 	}

function mouseupShip(e){
	if(glob.clicked===undefined){ return; }
	var [cx, cy] = [e.clientX, e.clientY]
	var [ex, ey] = [mex   , mey   ]
	console.log('cx ex, cy, ey', cx, cy, ex, ey)
	var [upx, upy] = [
		  offset(cx-ex)
		, offset(cy-ey)
	]
	//console.log('mouseup glob', glob)
// horizontal -> reverse -> result
	//console.log('upx upy', upx, upy)
	var [diffX, diffY] = [glob.diffX, glob.diffY]
	if(diffX > diffY){ 
		upx -= offset(diffX)
	}else{
		upy -= offset(diffY)
	}
	console.log('upx upy2 ', diffX, diffY, upx, upy, getOrientation())
	console.log('upx upy3 ', )
	var len = getLength(glob.clicked)
	if([upx, upy].some(a => a<-1||a>8)){ 
		unsetglob()
		throw new Error('mouseShip: out of bounds ship')
	}
	sendShip(upx, upy, len, getOrientation())
	//sendHit(upx, upy)
	unsetglob();
	// and other logic like submitting ship to db etc
}

function getLength(elem){ return offset(shipheight(elem)>shipwidth(elem)?shipheight(elem):shipwidth(elem)) }
	
function sendShip(x, y, length, orien){
	console.log( '0 normal, 1 vert, 2 normal rev, 3 vert rev')
	alert(['sending ship x y length orien', x, y, length, orien].join(';'))
	//return;
	
	var theform = document.createElement('form')
	theform.style.display = 'none';
	theform.action = "/battleships/output.php"
	theform.method = "post"
	theform.type = "hidden"
	var mi = () => document.createElement('input')
	var fx = mi()
	var fy = mi()
	fx.name = "x"
	fy.name = "y"
	fx.value = x
	fy.value = y
	var sl = mi()
	sl.name = "length"
	sl.value = length
	var or = mi()
	or.name = "orientation"
	or.value = orien
	var gid = mi()
	var pw = mi()
	gid.name = "game_id"
	gid.value = game_id
	pw.name = "moisession"
	pw.value = moisession;
	([fx, fy, sl, or, gid, pw]).forEach(a => theform.appendChild(a))
	document.body.appendChild(theform)
	theform.submit()		
}

function mousedownShip(e){
	if(e.button!==0){ return; }
	
	console.log('mousedownShip is 0:', e.button===0)

	var el = e.target
	if(el.parentElement.id !== 'shipcontainer'){
		return mousedownShip({
			  clientX: e.clientX
			, clientY: e.clientY
			,  target: el.parentElement
			,  button: e.button
		});
	}
	if(el===document.body){ throw new Error('mousedownShip: recursed to body') }
	
	var [bx, by] = [getx(el), gety(el)]
	setglob(el, e.clientX-bx, e.clientY-by)
	
}
// qqq: when horizontal diffx, when vertical diffy
function mousemoveShip(e){
	e.preventDefault()
	if(glob.clicked===undefined){ return; }
	//if(!hasParentId('shipcontainer', e.target)){ unsetglob();return; }
	if(e.button!==0){ mouseupShip(e); }
	var [cliX, cliY] = [e.clientX, e.clientY]
	var el = glob.clicked
	el.style.position = 'absolute'
	el.style.zIndex = '10'
	var sc = document.getElementById('shipcontainer')
	el.style.left     = cliX - glob.diffX - getx(sc) //+ 2
	el.style.top	  = cliY - glob.diffY - gety(sc) //+ 2
	//console.log('GLOB', glob)
}

buyships.forEach(s => {
	s.addEventListener('mousedown'  , mousedownShip  )
})

function getOrientation(){
	var t = document.getElementsByClassName('rotater').item(0)
	if(      t.classList.contains('vertical') && t.classList.contains('rev')){ return 3;
	}else{if(t.classList.contains('rev'))																 		 { return 2;
	}else{if(t.classList.contains('vertical'))													 		 { return 1;
	}else	  																														     { return 0;
 	}}}
}

function cssrotate(t){
	if(t.classList.contains('vertical') && t.classList.contains('rev')){  // ship
		t.classList.remove('vertical')
		t.classList.remove('rev')
	}else{if(t.classList.contains('rev')){ // ship vertical rev
		t.classList.add('vertical')
	}else{if(t.classList.contains('vertical')){ // ship rev
		t.classList.add('rev')
		t.classList.remove('vertical')
	}else{ // ship vertical
			t.classList.add('vertical')
	}}}
}

document.body.addEventListener('mouseup'    , mouseupShip    )
document.body.addEventListener('mouseleave' , mouseupShip    )
document.body.addEventListener('mousemove'  , mousemoveShip  )
document.getElementById('rotateships').addEventListener('click', e => {
	buyships.forEach(s => {
		var [h, w] = [
			  Number(s.style.height.slice(0,-2))
			, Number(s.style.width.slice(0,-2))
		]
		s.style.height = Number(h)>Number(w)?'40px':w+'px';
		s.style.width = Number(w)>Number(h)?'40px':h+'px';

		arrayof(s.children).forEach(t => {
			var [ml, mt] = [
				  Number(t.style.marginLeft.slice(0,-2))
				, Number(t.style.marginTop.slice(0,-2) )
			]
			t.style.marginLeft = mt+'px'
			t.style.marginTop = ml+'px'
			cssrotate(t);
		})
	}) // end buyships foreach.
	var rotateme = 
			(
			    e.target.parentElement.classList.contains('container')
			  ? e.target.parentElement
			  : e.target.getElementsByClassName('container').item(0)
			);
	cssrotate(rotateme)
})
