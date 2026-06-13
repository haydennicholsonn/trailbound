import { bindShatterButtons, initBootSequence, initCursorTrail, initEyeTracking } from "./effects.js";

const motionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
const getReducedMotion = () => motionQuery.matches;

function setActiveScene(scene, index, total) {
  const readout = document.querySelector("[data-active-scene]");
  const railDots = [...document.querySelectorAll(".rail-dot")];
  const railFill = document.querySelector(".rail-fill");

  document.body.dataset.scene = scene;

  if (readout) {
    readout.textContent = scene;
  }

  railDots.forEach((dot) => {
    dot.classList.toggle("is-active", dot.dataset.sceneLabel === scene);
  });

  if (railFill && total > 1) {
    railFill.style.height = `${(index / (total - 1)) * 100}%`;
  }
}

function scrollToTarget(selector) {
  const target = document.querySelector(selector);

  if (!target) {
    return;
  }

  const top = Math.max(0, target.getBoundingClientRect().top + window.scrollY);
  const smooth = !getReducedMotion();

  window.scrollTo({
    top,
    behavior: smooth ? "smooth" : "auto",
  });

  if (smooth) {
    window.setTimeout(() => {
      if (Math.abs(window.scrollY - top) > 36) {
        window.scrollTo({ top, behavior: "auto" });
      }
    }, 720);
  }

  target.focus?.({
    preventScroll: true,
  });
}

function initSceneObserver() {
  const sections = [...document.querySelectorAll("main section[data-scene]")];

  if (!sections.length) {
    return;
  }

  const activate = (section) => {
    const index = sections.indexOf(section);
    section.classList.add("is-visible");
    setActiveScene(section.dataset.scene, index, sections.length);
  };

  activate(sections[0]);

  if (!("IntersectionObserver" in window)) {
    sections.forEach(activate);
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          activate(entry.target);
        }
      });
    },
    {
      root: null,
      rootMargin: "-15% 0px -35% 0px",
      threshold: 0.38,
    },
  );

  sections.forEach((section) => observer.observe(section));
}

function bindSceneRail() {
  document.querySelectorAll("[data-jump]").forEach((control) => {
    control.addEventListener("click", () => {
      scrollToTarget(control.dataset.jump);
    });
  });
}

function bindRestart() {
  const restart = document.querySelector(".js-restart");

  if (!restart) {
    return;
  }

  restart.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: getReducedMotion() ? "auto" : "smooth" });
    window.setTimeout(() => window.location.reload(), getReducedMotion() ? 40 : 520);
  });
}

function init() {
  document.documentElement.classList.toggle("reduced-motion", getReducedMotion());

  initSceneObserver();
  bindSceneRail();
  bindRestart();
  initBootSequence({ reducedMotion: getReducedMotion });
  initEyeTracking({ reducedMotion: getReducedMotion });
  initCursorTrail({ reducedMotion: getReducedMotion });
  bindShatterButtons({
    reducedMotion: getReducedMotion,
    onNavigate: scrollToTarget,
  });

  motionQuery.addEventListener("change", () => {
    document.documentElement.classList.toggle("reduced-motion", getReducedMotion());
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
  init();
}
