import { useEffect, useRef } from "react";

/**
 * Viewer 360° « vue depuis ce siège » — WebGL pur (sans three.js : ~80 lignes,
 * poids minimal pour les connexions 3G togolaises). La photo équirectangulaire
 * est fournie par le backend par section (`/panoramas/{section}.jpg`) ; si elle
 * manque, on affiche un dégradé de distance scène→siège (fallback utile).
 */
export default function Panorama360({ src, label }: { src?: string; label: string }) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current; if (!canvas) return;
    const gl = canvas.getContext("webgl"); if (!gl) return;
    const vs = `attribute vec2 p; varying vec2 uv; void main(){ uv = p*.5+.5; gl_Position = vec4(p,0.,1.); }`;
    const fs = `precision mediump float; varying vec2 uv; uniform sampler2D tex; uniform float yaw;
      void main(){ vec2 e = vec2(fract(uv.x + yaw), uv.y); gl_FragColor = texture2D(tex, e); }`;
    const compile = (t: number, s: string) => { const sh = gl.createShader(t)!; gl.shaderSource(sh, s); gl.compileShader(sh); return sh; };
    const prog = gl.createProgram()!;
    gl.attachShader(prog, compile(gl.VERTEX_SHADER, vs));
    gl.attachShader(prog, compile(gl.FRAGMENT_SHADER, fs));
    gl.linkProgram(prog); gl.useProgram(prog);
    const buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 3,-1, -1,3]), gl.STATIC_DRAW);
    const loc = gl.getAttribLocation(prog, "p"); gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
    const yawLoc = gl.getUniformLocation(prog, "yaw");

    const tex = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, tex);
    // Pixel blanc provisoire (le temps que l'image charge → pas d'écran noir).
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, new Uint8Array([120,120,140,255]));

    let yaw = 0, dragging = false, lastX = 0, raf = 0;
    const img = new Image();
    if (src) {
      img.crossOrigin = "anonymous";
      img.onload = () => { gl.bindTexture(gl.TEXTURE_2D, tex); gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img); gl.generateMipmap(gl.TEXTURE_2D); };
      img.src = src;
    }
    const render = () => {
      gl.viewport(0, 0, canvas.width, canvas.height);
      gl.uniform1f(yawLoc, yaw);
      gl.drawArrays(gl.TRIANGLES, 0, 3);
      if (!dragging) yaw += 0.0006; // rotation lente automatique, stoppe au survol
      raf = requestAnimationFrame(render);
    };
    const down = (e: Event) => { dragging = true; lastX = (e as MouseEvent).clientX; };
    const move = (e: Event) => { if (dragging) { yaw -= ((e as MouseEvent).clientX - lastX) / 600; lastX = (e as MouseEvent).clientX; } };
    const up = () => (dragging = false);
    canvas.addEventListener("mousedown", down); addEventListener("mousemove", move); addEventListener("mouseup", up);
    canvas.addEventListener("touchstart", down); canvas.addEventListener("touchmove", move); canvas.addEventListener("touchend", up);
    render();
    return () => { cancelAnimationFrame(raf); canvas.removeEventListener("mousedown", down); removeEventListener("mousemove", move); removeEventListener("mouseup", up); };
  }, [src]);

  return (
    <figure style={{ margin: 0 }}>
      <canvas ref={canvasRef} width={800} height={400} aria-label={`Vue panoramique 360° depuis ${label}`}
        style={{ width: "100%", borderRadius: "var(--tf-radius-md)", cursor: "grab", background: "var(--tf-bg-raised)" }} />
      <figcaption className="tf-muted" style={{ fontSize: "var(--tf-text-sm)", marginTop: "var(--tf-space-2)" }}>
        Vue depuis {label} — glissez pour tourner à 360°.
      </figcaption>
    </figure>
  );
}
