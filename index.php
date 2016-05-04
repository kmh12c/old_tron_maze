<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
		<title>TRON MAZE 3D</title>

		<style type="text/css">
			#loadingtext {
				position:absolute;
				top:250px;
				left:150px;
				font-size:2em;
				color: white;
			}
			
			input#un_mute {
				display: none;
			}

			.unmute img {
				display: none;
			}

			input#un_mute:checked ~ .unmute img {
				display: initial;
			}

			input#un_mute:checked ~ .mute img {
				display: none;
			}

			canvas {
				border: none;
				width: 1300;
				height: 800;
			}

		</style>
		<script>
	
    	</script>
	</head>

	<body onload="webGLStart();">
		<audio id="myAudio" autoplay="autoplay">
			<source src="music/flynn.ogg" type="audio/ogg">
			<source src="music/flynn.mp3" type="audio/mpeg">
			Your user agent does not support the HTML5 Audio element.
		</audio>

		<script>
			function aud_play_pause() {
				var myAudio = document.getElementById("myAudio");
				if (myAudio.paused) {
					myAudio.play();
				} 
				else {
					myAudio.pause();
				}
			}
		</script>

		<img src="img/logo.png" alt="logo.png" title="Tron Maze Logo">

		<!--http://stackoverflow.com/questions/28300316/simple-background-music-for-website-->
		<input type="checkbox" name="un_mute" id="un_mute" onclick="aud_play_pause()">
			<label for="un_mute" class="unmute">
				<img src="img/Mute_Icon.png" alt="Mute_Icon.png" title="Mute icon">
			</label>
			<label for="un_mute" class="mute">
				<img src="img/Speaker_Icon.png" alt="Speaker_Icon.png" alt="Speaker_Icon.svg" title="Unmute/speaker icon">
			</label>

		<br/><br/>
		Use the cursor keys or WASD to run around, <code>Space Bar</code> to jump, and <code>Page Up</code>/<code>Page Down</code> to look up and down.<br/><br/>

		<canvas id="canvas"></canvas>
		
		<div id="loadingtext"></div>
		
		<div id="mazeContents">
			&nbsp;
		</div>

		<script type="text/javascript" src="js/glMatrix-0.9.5.min.js"></script>
		<script type="text/javascript" src="js/webgl-utils.js"></script>

		<script type="text/javascript" src="js/decomp.js"></script>
		
		<script type="text/javascript" src="js/glge-compiled-min.js"></script>
		<script type="text/javascript" src="js/glge_flycamera.js"></script>
		<script type="text/javascript" src="js/jquery-1.4.4.min.js"></script>
		<script type="text/javascript" src="js/jquery-ui-1.8.7.custom.min.js"></script>
		<script type="text/javascript" src="js/export.js"></script>
		<script type="text/javascript" src="js/main.js"></script>

		<script id="shader-fs" type="x-shader/x-fragment">
			precision mediump float;

			varying vec2 vTextureCoord;

			uniform sampler2D uSampler;

			void main(void) {
				gl_FragColor = texture2D(uSampler, vec2(vTextureCoord.s, vTextureCoord.t));
			}
		</script>

		<script id="shader-vs" type="x-shader/x-vertex">
			attribute vec3 aVertexPosition;
			attribute vec2 aTextureCoord;

			uniform mat4 uMVMatrix;
			uniform mat4 uPMatrix;


			varying vec2 vTextureCoord;

			void main(void) {
				gl_Position = uPMatrix * uMVMatrix * vec4(aVertexPosition, 1.0);
				vTextureCoord = aTextureCoord;
			}
		</script>


		<script type="text/javascript">
		
			var jump = {
				isPerforming : false,
				hPos : 0,
				startSpeed : 3,
				accel : 9.8,
				timePoint : 0
			}

			var gl;

			function initGL(canvas) {
				try {
					gl = canvas.getContext("experimental-webgl");
					gl.viewportWidth = canvas.width;
					gl.viewportHeight = canvas.height;
				} catch (e) {
				}
				if (!gl) {
					alert("Could not initialise WebGL, sorry :-(");
				}
			}


			function getShader(gl, id) {
				var shaderScript = document.getElementById(id);
				if (!shaderScript) {
					return null;
				}

				var str = "";
				var k = shaderScript.firstChild;
				while (k) {
					if (k.nodeType == 3) {
						str += k.textContent;
					}
					k = k.nextSibling;
				}

				var shader;
				if (shaderScript.type == "x-shader/x-fragment") {
					shader = gl.createShader(gl.FRAGMENT_SHADER);
				} else if (shaderScript.type == "x-shader/x-vertex") {
					shader = gl.createShader(gl.VERTEX_SHADER);
				} else {
					return null;
				}

				gl.shaderSource(shader, str);
				gl.compileShader(shader);

				if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
					alert(gl.getShaderInfoLog(shader));
					return null;
				}

				return shader;
			}


			var shaderProgram;

			function initShaders() {
				var fragmentShader = getShader(gl, "shader-fs");
				var vertexShader = getShader(gl, "shader-vs");

				shaderProgram = gl.createProgram();
				gl.attachShader(shaderProgram, vertexShader);
				gl.attachShader(shaderProgram, fragmentShader);
				gl.linkProgram(shaderProgram);

				if (!gl.getProgramParameter(shaderProgram, gl.LINK_STATUS)) {
					alert("Could not initialise shaders");
				}

				gl.useProgram(shaderProgram);

				shaderProgram.vertexPositionAttribute = gl.getAttribLocation(shaderProgram, "aVertexPosition");
				gl.enableVertexAttribArray(shaderProgram.vertexPositionAttribute);

				shaderProgram.textureCoordAttribute = gl.getAttribLocation(shaderProgram, "aTextureCoord");
				gl.enableVertexAttribArray(shaderProgram.textureCoordAttribute);

				shaderProgram.pMatrixUniform = gl.getUniformLocation(shaderProgram, "uPMatrix");
				shaderProgram.mvMatrixUniform = gl.getUniformLocation(shaderProgram, "uMVMatrix");
				shaderProgram.samplerUniform = gl.getUniformLocation(shaderProgram, "uSampler");
			}


			function handleLoadedTexture(texture) {
				gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
				gl.bindTexture(gl.TEXTURE_2D, texture);
				gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, texture.image);
				gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
				gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);

				gl.bindTexture(gl.TEXTURE_2D, null);
			}


			var textureArray = new Array();

			function initTextures() {
				textureArray["floor"] = loadTextures("img/floor.png");
				textureArray["wall"] = loadTextures("img/wall.png");
			}
			
			function loadTextures(texture_location) {
				var texture = gl.createTexture();
				texture.image = new Image();
				texture.image.onload = function () {
					handleLoadedTexture(texture)
				}

				texture.image.src = texture_location;
				
				return texture;
			}


			var mvMatrix = mat4.create();
			var mvMatrixStack = [];
			var pMatrix = mat4.create();

			function mvPushMatrix() {
				var copy = mat4.create();
				mat4.set(mvMatrix, copy);
				mvMatrixStack.push(copy);
			}

			function mvPopMatrix() {
				if (mvMatrixStack.length == 0) {
					throw "Invalid popMatrix!";
				}
				mvMatrix = mvMatrixStack.pop();
			}


			function setMatrixUniforms() {
				gl.uniformMatrix4fv(shaderProgram.pMatrixUniform, false, pMatrix);
				gl.uniformMatrix4fv(shaderProgram.mvMatrixUniform, false, mvMatrix);
			}


			function degToRad(degrees) {
				return degrees * Math.PI / 180;
			}


			var currentlyPressedKeys = {};

			function handleKeyDown(event) {
				event.preventDefault();
				currentlyPressedKeys[event.keyCode] = true;
				if (event.keyCode==32) jump.isPerforming = true;
			}


			function handleKeyUp(event) {
				event.preventDefault();
				currentlyPressedKeys[event.keyCode] = false;
			}


			var pitch = 0;
			var pitchRate = 0;

			var yaw = 0;
			var yawRate = 0;

			var xPos = 0;
			var yPos = 0.4;
			var zPos = 0;

			var speed = 0;

			function handleKeys() {
				if (currentlyPressedKeys[33]) {
					// Page Up
					pitchRate = 0.1;
				} else if (currentlyPressedKeys[34]) {
					// Page Down
					pitchRate = -0.1;
				} else {
					pitchRate = 0;
				}

				if (currentlyPressedKeys[37] || currentlyPressedKeys[65]) {
					// Left cursor key or A
					yawRate = 0.15;
				} else if (currentlyPressedKeys[39] || currentlyPressedKeys[68]) {
					// Right cursor key or D
					yawRate = -0.15;
				} else {
					yawRate = 0;
				}

				if (currentlyPressedKeys[38] || currentlyPressedKeys[87]) {
					// Up cursor key or W
					speed = 0.003;
				} else if (currentlyPressedKeys[40] || currentlyPressedKeys[83]) {
					// Down cursor key
					speed = -0.003;
				} else {
					speed = 0;
				}

			}


			var worldVertexPositionBuffer = null;
			var worldVertexTextureCoordBuffer = null;
			var worldVertexPositionBufferFLOOR = null;
			var worldVertexTextureCoordBufferFLOOR = null;

			
			var maze = "";
			
			function loadWorld() {
				var request = new XMLHttpRequest();
				request.open("GET", "maze1.dat");
				request.onreadystatechange = function () {
					if (request.readyState == 4) 
					{
						maze = decompose(request.responseText);
						//document.getElementById("mazeContents").innerHTML = dump(maze);
						//alert(dump(maze[7][8]));
						handleLoadedWorld(maze);
					}
				}
				request.send();
			}

			
			function handleLoadedWorld(data) {
				var vertexCount = 0;
				var vertexPositions = [];
				var vertexTextureCoords = [];
				var vertexCountFLOOR = 0;
				var vertexPositionsFLOOR = [];
				var vertexTextureCoordsFLOOR = [];
				
				

				for(var i=0; i < maze["r"]; i++) 
				{
					for(var j=0; j < maze["c"]; j++)
					{
						currentTile=maze[i][j]; //assoc array. "p" = passable (true/false), "t" = tile (img location)
						
						//============================
						//
						//			Front
						//
						//============================
						if(i==maze["r"]-1 || (maze[i][j]["p"] == "true" && maze[i+1][j]["p"] == "false") || (maze[i][j]["p"] == "false" && maze[i+1][j]["p"] == "true"))
						{
							if(i==maze["r"]-1 && typeof maze[i][j]["l"] !== 'undefined') //link to the next map, don't draw exit.
							{ }
							else
							{
							//============================
							//
							//			Triangle 1
							//
							//============================
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexCount += 3;
							
							//============================
							//
							//			Triangle 2
							//
							//============================
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexCount += 3;
							}
						}
						
						//============================
						//
						//			Left
						//
						//============================
						if(j==0 && typeof maze[i][j]["l"] === 'undefined')
						{
							//============================
							//
							//			Triangle 1
							//
							//============================
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexCount += 3;
							
							//============================
							//
							//			Triangle 2
							//
							//============================
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexCount += 3;

						}
						
						//============================
						//
						//			Back
						//
						//============================
						if(i==0 && typeof maze[i][j]["l"] === 'undefined') 
						{
							//============================
							//
							//			Triangle 1
							//
							//============================
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexCount += 3;
							
							//============================
							//
							//			Triangle 2
							//
							//============================
							vertexPositions.push(parseFloat(j)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexCount += 3;
						}

						
						//============================
						//
						//			Right
						//
						//============================
						if(j==maze["c"]-1 || (maze[i][j]["p"] == "true" && maze[i][j+1]["p"] == "false") || (maze[i][j]["p"] == "false" && maze[i][j+1]["p"] == "true"))
						{
							if(j==maze["c"]-1 && typeof maze[i][j]["l"] !== 'undefined') //link to the next map, don't draw exit.
							{ }
							else
							{
							//============================
							//
							//			Triangle 1
							//
							//============================
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexCount += 3;
							
							//============================
							//
							//			Triangle 2
							//
							//============================
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoords.push(parseFloat(0.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(1)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(1.0)); // V
							
							vertexPositions.push(parseFloat(j+1)); // X
							vertexPositions.push(parseFloat(0)); // Y
							vertexPositions.push(parseFloat(i*-1)); // Z
							vertexTextureCoords.push(parseFloat(1.0)); // U
							vertexTextureCoords.push(parseFloat(0.0)); // V
							
							vertexCount += 3;
							}
						}

			
						//============================
						//
						//			Bottom
						//
						//============================	

							//============================
							//
							//			Triangle 1
							//
							//============================
							vertexPositionsFLOOR.push(parseFloat(j)); // X
							vertexPositionsFLOOR.push(parseFloat(0)); // Y
							vertexPositionsFLOOR.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoordsFLOOR.push(parseFloat(0.0)); // U
							vertexTextureCoordsFLOOR.push(parseFloat(1.0)); // V
							
							vertexPositionsFLOOR.push(parseFloat(j)); // X
							vertexPositionsFLOOR.push(parseFloat(0)); // Y
							vertexPositionsFLOOR.push(parseFloat(i*-1)); // Z
							vertexTextureCoordsFLOOR.push(parseFloat(0.0)); // U
							vertexTextureCoordsFLOOR.push(parseFloat(0.0)); // V
							
							vertexPositionsFLOOR.push(parseFloat(j+1)); // X
							vertexPositionsFLOOR.push(parseFloat(0)); // Y
							vertexPositionsFLOOR.push(parseFloat(i*-1)); // Z
							vertexTextureCoordsFLOOR.push(parseFloat(1.0)); // U
							vertexTextureCoordsFLOOR.push(parseFloat(0.0)); // V
							
							vertexCountFLOOR += 3;
							
							//============================
							//
							//			Triangle 2
							//
							//============================
							vertexPositionsFLOOR.push(parseFloat(j)); // X
							vertexPositionsFLOOR.push(parseFloat(0)); // Y
							vertexPositionsFLOOR.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoordsFLOOR.push(parseFloat(0.0)); // U
							vertexTextureCoordsFLOOR.push(parseFloat(1.0)); // V
							
							vertexPositionsFLOOR.push(parseFloat(j+1)); // X
							vertexPositionsFLOOR.push(parseFloat(0)); // Y
							vertexPositionsFLOOR.push(parseFloat((i*-1)-1)); // Z
							vertexTextureCoordsFLOOR.push(parseFloat(1.0)); // U
							vertexTextureCoordsFLOOR.push(parseFloat(1.0)); // V
							
							vertexPositionsFLOOR.push(parseFloat(j+1)); // X
							vertexPositionsFLOOR.push(parseFloat(0)); // Y
							vertexPositionsFLOOR.push(parseFloat(i*-1)); // Z
							vertexTextureCoordsFLOOR.push(parseFloat(1.0)); // U
							vertexTextureCoordsFLOOR.push(parseFloat(0.0)); // V
							
							vertexCountFLOOR += 3;
					}
				}

				//WALL
				worldVertexPositionBuffer = gl.createBuffer();
				gl.bindBuffer(gl.ARRAY_BUFFER, worldVertexPositionBuffer);
				gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertexPositions), gl.STATIC_DRAW);
				worldVertexPositionBuffer.itemSize = 3;
				worldVertexPositionBuffer.numItems = vertexCount;

				worldVertexTextureCoordBuffer = gl.createBuffer();
				gl.bindBuffer(gl.ARRAY_BUFFER, worldVertexTextureCoordBuffer);
				gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertexTextureCoords), gl.STATIC_DRAW);
				worldVertexTextureCoordBuffer.itemSize = 2;
				worldVertexTextureCoordBuffer.numItems = vertexCount;

				//FLOOR
				worldVertexPositionBufferFLOOR = gl.createBuffer();
				gl.bindBuffer(gl.ARRAY_BUFFER, worldVertexPositionBufferFLOOR);
				gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertexPositionsFLOOR), gl.STATIC_DRAW);
				worldVertexPositionBufferFLOOR.itemSize = 3;
				worldVertexPositionBufferFLOOR.numItems = vertexCountFLOOR;

				worldVertexTextureCoordBufferFLOOR = gl.createBuffer();
				gl.bindBuffer(gl.ARRAY_BUFFER, worldVertexTextureCoordBufferFLOOR);
				gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(vertexTextureCoordsFLOOR), gl.STATIC_DRAW);
				worldVertexTextureCoordBufferFLOOR.itemSize = 2;
				worldVertexTextureCoordBufferFLOOR.numItems = vertexCountFLOOR;

				document.getElementById("loadingtext").textContent = "";
			}
			
			function drawScene() {
				gl.viewport(0, 0, gl.viewportWidth, gl.viewportHeight);
				gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);

				//FLOOR
				if (worldVertexTextureCoordBufferFLOOR == null || worldVertexPositionBufferFLOOR == null) {
					return;
				}

				mat4.perspective(45, gl.viewportWidth / gl.viewportHeight, 0.1, 100.0, pMatrix);

				mat4.identity(mvMatrix);

				mat4.rotate(mvMatrix, degToRad(-pitch), [1, 0, 0]);
				mat4.rotate(mvMatrix, degToRad(-yaw), [0, 1, 0]);
				mat4.translate(mvMatrix, [-xPos, -yPos-jump.hPos, -zPos]);
				
				gl.activeTexture(gl.TEXTURE0);
				gl.bindTexture(gl.TEXTURE_2D, textureArray["floor"]);
				gl.uniform1i(shaderProgram.samplerUniform, 0);

				gl.bindBuffer(gl.ARRAY_BUFFER, worldVertexTextureCoordBufferFLOOR);
				gl.vertexAttribPointer(shaderProgram.textureCoordAttribute, worldVertexTextureCoordBufferFLOOR.itemSize, gl.FLOAT, false, 0, 0);

				gl.bindBuffer(gl.ARRAY_BUFFER, worldVertexPositionBufferFLOOR);
				gl.vertexAttribPointer(shaderProgram.vertexPositionAttribute, worldVertexPositionBufferFLOOR.itemSize, gl.FLOAT, false, 0, 0);

				setMatrixUniforms();
				gl.drawArrays(gl.TRIANGLES, 0, worldVertexPositionBufferFLOOR.numItems);
				
				//WALL
				if (worldVertexTextureCoordBuffer == null || worldVertexPositionBuffer == null) {
					return;
				}

				mat4.perspective(45, gl.viewportWidth / gl.viewportHeight, 0.1, 100.0, pMatrix);

				mat4.identity(mvMatrix);

				mat4.rotate(mvMatrix, degToRad(-pitch), [1, 0, 0]);
				mat4.rotate(mvMatrix, degToRad(-yaw), [0, 1, 0]);
				mat4.translate(mvMatrix, [-xPos, -yPos-jump.hPos, -zPos]);
				
				gl.activeTexture(gl.TEXTURE0);
				gl.bindTexture(gl.TEXTURE_2D, textureArray["wall"]);
				gl.uniform1i(shaderProgram.samplerUniform, 0);

				gl.bindBuffer(gl.ARRAY_BUFFER, worldVertexTextureCoordBuffer);
				gl.vertexAttribPointer(shaderProgram.textureCoordAttribute, worldVertexTextureCoordBuffer.itemSize, gl.FLOAT, false, 0, 0);

				gl.bindBuffer(gl.ARRAY_BUFFER, worldVertexPositionBuffer);
				gl.vertexAttribPointer(shaderProgram.vertexPositionAttribute, worldVertexPositionBuffer.itemSize, gl.FLOAT, false, 0, 0);

				setMatrixUniforms();
				gl.drawArrays(gl.TRIANGLES, 0, worldVertexPositionBuffer.numItems);
			}
			
			


			var lastTime = 0;
			// Used to make us "jog" up and down as we move forward.
			var joggingAngle = 0;

			function animate() {
				var timeNow = new Date().getTime();
				if (lastTime != 0) {
					var elapsed = timeNow - lastTime;

					if (speed != 0) {
						xPos -= Math.sin(degToRad(yaw)) * speed * elapsed;
						zPos -= Math.cos(degToRad(yaw)) * speed * elapsed;

						joggingAngle += elapsed * 0.6; // 0.6 "fiddle factor" - makes it feel more realistic :)
						yPos = Math.sin(degToRad(joggingAngle)) / 20 + 0.4
					}

					yaw += yawRate * elapsed;
					pitch += pitchRate * elapsed;

					if (jump.isPerforming)
					{
						jump.timePoint += elapsed*0.001;
						jump.hPos = jump.startSpeed * jump.timePoint - jump.accel * jump.timePoint * jump.timePoint / 2;
						if (jump.hPos<0.0)
						{
							jump.timePoint = 0;
							jump.hPos = 0;
							jump.isPerforming = false;
						}
					}

				}
				lastTime = timeNow;
			}


			function tick() {
				requestAnimFrame(tick);
				handleKeys();
				drawScene();
				animate();
			}



			function webGLStart() {
				var canvas = document.getElementById("canvas");
				initGL(canvas);
				initShaders();
				initTextures();
				loadWorld();

				gl.clearColor(0.0, 0.0, 0.0, 1.0);
				gl.enable(gl.DEPTH_TEST);

				document.onkeydown = handleKeyDown;
				document.onkeyup = handleKeyUp;

				tick();
			}


		
		</script>

	</body>

</html>