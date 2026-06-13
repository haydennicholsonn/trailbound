export function initBootSequence({ reducedMotion }) {
  const screen = document.querySelector("#boot-screen");
  const lines = [...document.querySelectorAll(".boot-line")];
  const skip = document.querySelector(".boot-skip");

  if (!screen) {
    return;
  }

  let finished = false;
  const timers = [];

  const finish = () => {
    if (finished) {
      return;
    }

    finished = true;
    timers.forEach((timer) => window.clearTimeout(timer));
    lines.forEach((line) => line.classList.add("is-active"));
    screen.classList.add("is-hidden");

    window.setTimeout(() => {
      screen.setAttribute("aria-hidden", "true");
      screen.hidden = true;
    }, 460);
  };

  if (reducedMotion()) {
    finish();
    return;
  }

  lines.forEach((line, index) => {
    timers.push(
      window.setTimeout(() => {
        line.classList.add("is-active");
      }, 160 + index * 260),
    );
  });

  timers.push(window.setTimeout(finish, 1460));
  skip?.addEventListener("click", finish, { once: true });
}

export function initCursorTrail({ reducedMotion }) {
  const canvas = document.querySelector("#cursor-trail");

  if (!canvas || reducedMotion()) {
    return;
  }

  const context = canvas.getContext("2d", { alpha: true });

  if (!context) {
    return;
  }

  const particles = [];
  const palette = ["#38bdf8", "#34d399", "#8b5cf6"];
  let raf = 0;
  let lastMove = 0;
  let lastScroll = window.scrollY;
  let lastScrollBurst = 0;
  let width = 0;
  let height = 0;
  let dpr = 1;

  const resize = () => {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    context.setTransform(dpr, 0, 0, dpr, 0, 0);
  };

  const addParticle = (x, y, boost = 1) => {
    particles.push({
      x,
      y,
      vx: (Math.random() - 0.5) * 0.9 * boost,
      vy: (Math.random() - 0.5) * 0.9 * boost,
      life: 1,
      size: (2 + Math.random() * 3.5) * Math.min(1.6, boost),
      color: palette[Math.floor(Math.random() * palette.length)],
    });

    if (particles.length > 120) {
      particles.splice(0, particles.length - 120);
    }
  };

  const burst = (x, y, count = 3, boost = 1) => {
    for (let index = 0; index < count; index += 1) {
      addParticle(
        x + (Math.random() - 0.5) * 22,
        y + (Math.random() - 0.5) * 22,
        boost,
      );
    }
  };

  const render = () => {
    context.clearRect(0, 0, width, height);

    for (let index = particles.length - 1; index >= 0; index -= 1) {
      const particle = particles[index];
      particle.x += particle.vx;
      particle.y += particle.vy;
      particle.life -= 0.032;

      if (particle.life <= 0) {
        particles.splice(index, 1);
        continue;
      }

      context.globalAlpha = particle.life * 0.72;
      context.fillStyle = particle.color;
      context.shadowBlur = 12;
      context.shadowColor = particle.color;
      context.beginPath();
      context.arc(particle.x, particle.y, particle.size * particle.life, 0, Math.PI * 2);
      context.fill();
    }

    context.globalAlpha = 1;
    context.shadowBlur = 0;
    raf = window.requestAnimationFrame(render);
  };

  resize();
  raf = window.requestAnimationFrame(render);

  window.addEventListener("resize", resize, { passive: true });
  window.addEventListener(
    "pointermove",
    (event) => {
      const now = performance.now();

      if (now - lastMove < 28 || reducedMotion()) {
        return;
      }

      lastMove = now;
      burst(event.clientX, event.clientY, event.pointerType === "touch" ? 3 : 2, event.pointerType === "touch" ? 1.24 : 1);
    },
    { passive: true },
  );
  window.addEventListener(
    "touchstart",
    (event) => {
      if (reducedMotion()) {
        return;
      }

      [...event.touches].slice(0, 2).forEach((touch) => burst(touch.clientX, touch.clientY, 5, 1.2));
    },
    { passive: true },
  );
  window.addEventListener(
    "touchmove",
    (event) => {
      const now = performance.now();

      if (now - lastMove < 36 || reducedMotion()) {
        return;
      }

      lastMove = now;
      [...event.touches].slice(0, 2).forEach((touch) => burst(touch.clientX, touch.clientY, 3, 1.18));
    },
    { passive: true },
  );
  window.addEventListener(
    "scroll",
    () => {
      const now = performance.now();

      if (now - lastScrollBurst < 48 || reducedMotion()) {
        lastScroll = window.scrollY;
        return;
      }

      const delta = window.scrollY - lastScroll;
      lastScroll = window.scrollY;

      if (Math.abs(delta) < 4) {
        return;
      }

      lastScrollBurst = now;
      const direction = delta > 0 ? 1 : -1;
      const x = width * (0.18 + Math.random() * 0.64);
      const y = direction > 0 ? height - 42 - Math.random() * 82 : 42 + Math.random() * 82;
      burst(x, y, Math.min(7, 2 + Math.floor(Math.abs(delta) / 45)), 1.35);
    },
    { passive: true },
  );

  window.addEventListener("beforeunload", () => window.cancelAnimationFrame(raf), { once: true });
}

export function initEyeTracking({ reducedMotion }) {
  const eyes = [...document.querySelectorAll("[data-eye]")];

  if (!eyes.length || reducedMotion()) {
    return;
  }

  const root = document.documentElement;
  const pointer = {
    x: 0,
    y: 0,
    active: false,
    lastSeen: 0,
  };
  let lastScrollY = window.scrollY;
  let scrollVelocity = 0;
  let currentX = 0;
  let currentY = 0;
  let blinkTimer = 0;
  let raf = 0;

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  const setPointer = (clientX, clientY) => {
    pointer.x = clamp((clientX / Math.max(1, window.innerWidth) - 0.5) * 2, -1, 1);
    pointer.y = clamp((clientY / Math.max(1, window.innerHeight) - 0.5) * 2, -1, 1);
    pointer.active = true;
    pointer.lastSeen = performance.now();
  };

  const blink = () => {
    eyes.forEach((eye) => eye.classList.add("is-blinking"));
    window.setTimeout(() => eyes.forEach((eye) => eye.classList.remove("is-blinking")), 240);
    scheduleBlink();
  };

  const scheduleBlink = () => {
    window.clearTimeout(blinkTimer);
    blinkTimer = window.setTimeout(blink, 2800 + Math.random() * 5200);
  };

  const update = () => {
    const maxScroll = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    const scrollProgress = clamp(window.scrollY / maxScroll, 0, 1);
    const pointerFresh = pointer.active && performance.now() - pointer.lastSeen < 1800;
    const scrollX = Math.sin(scrollProgress * Math.PI * 2) * 0.44;
    const scrollY = clamp(scrollVelocity * 1.45 + (scrollProgress - 0.5) * 0.4, -1, 1);
    const targetX = pointerFresh ? pointer.x * 0.78 + scrollX * 0.22 : scrollX;
    const targetY = pointerFresh ? pointer.y * 0.62 + scrollY * 0.38 : scrollY;

    currentX += (targetX - currentX) * 0.14;
    currentY += (targetY - currentY) * 0.14;
    scrollVelocity *= 0.88;

    root.style.setProperty("--eye-look-x", currentX.toFixed(4));
    root.style.setProperty("--eye-look-y", currentY.toFixed(4));
    root.style.setProperty("--eye-scroll-tilt", `${(currentX * 1.8 + scrollVelocity * 8).toFixed(3)}deg`);

    raf = window.requestAnimationFrame(update);
  };

  window.addEventListener(
    "pointermove",
    (event) => {
      setPointer(event.clientX, event.clientY);
    },
    { passive: true },
  );
  window.addEventListener(
    "mousemove",
    (event) => {
      setPointer(event.clientX, event.clientY);
    },
    { passive: true },
  );
  window.addEventListener(
    "touchmove",
    (event) => {
      const touch = event.touches[0];

      if (touch) {
        setPointer(touch.clientX, touch.clientY);
      }
    },
    { passive: true },
  );
  window.addEventListener(
    "scroll",
    () => {
      const delta = window.scrollY - lastScrollY;
      lastScrollY = window.scrollY;
      scrollVelocity = clamp(delta / Math.max(80, window.innerHeight * 0.24), -1, 1);
      pointer.lastSeen = Math.max(0, pointer.lastSeen - 240);
    },
    { passive: true },
  );

  scheduleBlink();
  raf = window.requestAnimationFrame(update);

  window.addEventListener(
    "beforeunload",
    () => {
      window.clearTimeout(blinkTimer);
      window.cancelAnimationFrame(raf);
    },
    { once: true },
  );
}

export function bindShatterButtons({ reducedMotion, onNavigate }) {
  const buttons = [...document.querySelectorAll(".js-shatter")];

  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const target = button.dataset.target;

      if (!target) {
        return;
      }

      if (reducedMotion()) {
        onNavigate(target);
        return;
      }

      shatterButton(button, () => onNavigate(target));
    });
  });
}

function shatterButton(button, callback) {
  const rect = button.getBoundingClientRect();
  const columns = Math.max(5, Math.round(rect.width / 18));
  const rows = Math.max(3, Math.round(rect.height / 14));
  const shardWidth = rect.width / columns;
  const shardHeight = rect.height / rows;
  const colors = ["#38bdf8", "#8b5cf6", "#34d399", "#f8fafc"];
  let navigated = false;

  const runCallback = () => {
    if (navigated) {
      return;
    }

    navigated = true;
    callback();
  };

  button.classList.add("is-shattering");
  window.setTimeout(runCallback, 280);

  try {
    for (let row = 0; row < rows; row += 1) {
      for (let column = 0; column < columns; column += 1) {
        const shard = document.createElement("span");
        const x = rect.left + column * shardWidth + shardWidth / 2;
        const y = rect.top + row * shardHeight + shardHeight / 2;
        const angle = Math.atan2(y - (rect.top + rect.height / 2), x - (rect.left + rect.width / 2));
        const distance = 44 + Math.random() * 68;
        const rotate = Math.round((Math.random() - 0.5) * 220);

        shard.className = "button-shard";
        shard.style.left = `${x}px`;
        shard.style.top = `${y}px`;
        shard.style.width = `${Math.max(5, shardWidth - 4)}px`;
        shard.style.height = `${Math.max(5, shardHeight - 4)}px`;
        shard.style.background = colors[(row + column) % colors.length];
        document.body.appendChild(shard);

        const animation = shard.animate(
          [
            {
              opacity: 1,
              transform: "translate(-50%, -50%) scale(1) rotate(0deg)",
            },
            {
              opacity: 0,
              transform: `translate(calc(-50% + ${Math.cos(angle) * distance}px), calc(-50% + ${Math.sin(angle) * distance}px)) scale(0.45) rotate(${rotate}deg)`,
            },
          ],
          {
            duration: 430 + Math.random() * 140,
            easing: "cubic-bezier(.16, 1, .3, 1)",
            fill: "forwards",
          },
        );

        animation.onfinish = () => shard.remove();
      }
    }
  } catch (error) {
    runCallback();
  }

  window.setTimeout(() => button.classList.remove("is-shattering"), 620);
}
