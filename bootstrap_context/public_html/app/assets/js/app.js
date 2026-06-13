const classNames = {
  wayfarer: "Wayfarer",
  scout: "Scout",
  warden: "Warden",
  strider: "Strider",
  stormrunner: "Stormrunner",
};

const demo = {
  user: {
    display_name: "Wayfarer",
    avatar_class: "wayfarer",
    level: 1,
    xp_total: 0,
    xp_current_level: 0,
    skill_points: 0,
    coins: 0,
  },
  stats: {
    total_distance_m: 0,
    total_runs: 0,
    current_streak_weeks: 0,
    areas_discovered: 1,
    chests_opened: 0,
  },
  quests: [
    {
      name: "The First Road",
      description: "Complete one run of at least 2km.",
      quest_type: "main",
      status: "active",
      objective_type: "single_run_distance_m",
      progress_value: 0,
      target_value: 2000,
      reward_xp: 300,
      reward_chest_type: "old_travel_chest",
    },
    {
      name: "Footsteps Through Fog",
      description: "Complete 3 runs this week.",
      quest_type: "weekly",
      status: "active",
      objective_type: "weekly_run_count",
      progress_value: 0,
      target_value: 3,
      reward_xp: 600,
      reward_chest_type: "scout_chest",
    },
    {
      name: "Beyond the Watchfire",
      description: "Run 10km total and reach the Broken Shrine.",
      quest_type: "main",
      status: "active",
      objective_type: "total_distance_m",
      progress_value: 0,
      target_value: 10000,
      reward_xp: 700,
      reward_chest_type: "shrine_chest",
    },
  ],
  regions: [
    {
      code: "first_road",
      name: "The First Road",
      description: "Your journey begins here.",
      lore: "A cold road cuts through the mist. Every step wakes the world.",
      distance_required_m: 2000,
      progress_m: 0,
      is_unlocked: 1,
    },
    {
      code: "ashwood_gate",
      name: "Ashwood Gate",
      description: "An old forest gate hidden in fog.",
      lore: "The trees lean inward as if listening.",
      distance_required_m: 5000,
      progress_m: 0,
      is_unlocked: 0,
    },
    {
      code: "broken_shrine",
      name: "The Broken Shrine",
      description: "A ruined shrine beyond the first forest.",
      lore: "Stone, moss, and something still glowing beneath the cracks.",
      distance_required_m: 10000,
      progress_m: 0,
      is_unlocked: 0,
    },
    {
      code: "mistfen_crossing",
      name: "Mistfen Crossing",
      description: "A wetland path swallowed by pale mist.",
      lore: "The ground moves softly underfoot.",
      distance_required_m: 15000,
      progress_m: 0,
      is_unlocked: 0,
    },
    {
      code: "ember_hills",
      name: "The Ember Hills",
      description: "Hills lit by distant red fire.",
      lore: "At sunset the stones pulse like coals.",
      distance_required_m: 25000,
      progress_m: 0,
      is_unlocked: 0,
    },
  ],
  inventory: {
    chests: [],
    items: [],
  },
  skills: {
    available_skill_points: 0,
    nodes: [
      {
        id: 1,
        code: "steady_feet",
        name: "Steady Feet",
        branch: "endurance",
        description: "+5% XP from runs over 3km.",
        cost: 1,
      },
      {
        id: 2,
        code: "long_road",
        name: "Long Road",
        branch: "endurance",
        description: "+10% area progress from runs over 5km.",
        cost: 1,
      },
      {
        id: 3,
        code: "cartographer",
        name: "Cartographer",
        branch: "explorer",
        description: "+10% discovery progress on new routes.",
        cost: 1,
      },
      {
        id: 4,
        code: "hidden_trails",
        name: "Hidden Trails",
        branch: "explorer",
        description: "Small bonus chance to earn exploration chests.",
        cost: 1,
      },
      {
        id: 5,
        code: "quickstep",
        name: "Quickstep",
        branch: "tempo",
        description: "Bonus XP when beating your previous average pace.",
        cost: 1,
      },
    ],
    unlocked_nodes: [],
  },
};

const state = {
  authenticated: false,
  csrfToken: "",
  setupMessage: "",
  user: demo.user,
  stats: demo.stats,
  quests: demo.quests,
  regions: demo.regions,
  inventory: demo.inventory,
  skills: demo.skills,
  strava: {
    connected: false,
    athlete_id: null,
    last_sync_at: null,
  },
};

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];

function setText(selector, value) {
  const element = $(selector);

  if (element) {
    element.textContent = value;
  }
}

function km(meters) {
  return `${(Number(meters || 0) / 1000).toFixed(1)} km`;
}

function xpRequired(level) {
  return 500 + Number(level || 1) * Number(level || 1) * 75;
}

function percent(value, target) {
  const numericTarget = Number(target || 0);

  if (numericTarget <= 0) {
    return 0;
  }

  return Math.min(100, Math.max(0, (Number(value || 0) / numericTarget) * 100));
}

async function api(path, options = {}) {
  const response = await fetch(path, {
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
      ...(state.csrfToken ? { "X-CSRF-Token": state.csrfToken } : {}),
      ...(options.headers || {}),
    },
    ...options,
  });
  const payload = await response.json().catch(() => null);

  if (!payload || !payload.success) {
    const message = payload?.error?.message || `Request failed with status ${response.status}`;
    throw new Error(message);
  }

  return payload.data;
}

function showToast(message) {
  const toast = $("[data-toast]");

  if (!toast) {
    return;
  }

  toast.textContent = message;
  toast.classList.add("is-visible");
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => toast.classList.remove("is-visible"), 3200);
}

function setStatus(message, good = false) {
  const line = $("[data-status-line]");

  if (!line) {
    return;
  }

  line.textContent = message;
  line.classList.toggle("is-good", good);
}

function createListItem({ title, description, pills = [] }) {
  const item = document.createElement("article");
  item.className = "list-item";

  const strong = document.createElement("strong");
  strong.textContent = title;
  item.append(strong);

  if (description) {
    const paragraph = document.createElement("p");
    paragraph.textContent = description;
    item.append(paragraph);
  }

  if (pills.length) {
    const pillRow = document.createElement("div");
    pillRow.className = "pill-row";

    pills.forEach((pillText) => {
      const pill = document.createElement("span");
      pill.className = "pill";
      pill.textContent = pillText;
      pillRow.append(pill);
    });

    item.append(pillRow);
  }

  return item;
}

function renderDashboard() {
  const user = state.user || demo.user;
  const stats = state.stats || demo.stats;
  const currentQuest = state.quests[0] || demo.quests[0];
  const currentRegion = state.regions.find((region) => Number(region.is_unlocked) === 1) || state.regions[0] || demo.regions[0];
  const level = Number(user.level || 1);
  const required = xpRequired(level);
  const xp = Number(user.xp_current_level || 0);

  setText("[data-player-name]", user.display_name || "Wayfarer");
  setText("[data-character-class]", classNames[user.avatar_class] || "Wayfarer");
  setText("[data-class-state]", classNames[user.avatar_class] || "Wayfarer");
  setText("[data-level]", `Level ${level}`);
  setText("[data-skill-points]", `${Number(user.skill_points || 0)} skill points`);
  setText("[data-xp-label]", `${xp} / ${required} XP`);
  setText("[data-total-distance]", km(stats.total_distance_m));
  setText("[data-total-runs]", Number(stats.total_runs || 0).toString());
  setText("[data-weekly-streak]", `${Number(stats.current_streak_weeks || 0)} weeks`);
  setText("[data-current-quest-name]", currentQuest.name);
  setText("[data-current-quest-desc]", currentQuest.description);
  setText("[data-quest-status]", currentQuest.status || "Active");
  setText("[data-region-name]", currentRegion.name);
  setText("[data-region-state]", Number(currentRegion.is_unlocked) ? "Unlocked" : "Locked");
  setText("[data-region-lore]", currentRegion.lore || currentRegion.description);
  setText("[data-strava-state]", state.strava.connected ? "Connected" : "Disconnected");

  const questPercent = percent(currentQuest.progress_value, currentQuest.target_value);
  $("[data-xp-bar]").style.width = `${percent(xp, required)}%`;
  $("[data-quest-progress]").style.width = `${questPercent}%`;

  const targetLabel = String(currentQuest.objective_type || "").includes("distance")
    ? `${km(currentQuest.progress_value)} / ${km(currentQuest.target_value)}`
    : `${Number(currentQuest.progress_value || 0)} / ${Number(currentQuest.target_value || 0)}`;
  setText("[data-quest-progress-label]", targetLabel);

  $$("[data-class]").forEach((button) => {
    button.classList.toggle("is-selected", button.dataset.class === user.avatar_class);
  });
}

function renderRegions() {
  const list = $("[data-region-list]");
  list.replaceChildren();

  state.regions.forEach((region) => {
    list.append(createListItem({
      title: region.name,
      description: region.lore || region.description,
      pills: [
        Number(region.is_unlocked) ? "Unlocked" : "Locked",
        `${km(region.progress_m)} / ${km(region.distance_required_m)}`,
      ],
    }));
  });
}

function renderQuests() {
  const list = $("[data-quest-list]");
  list.replaceChildren();

  state.quests.forEach((quest) => {
    list.append(createListItem({
      title: quest.name,
      description: quest.description,
      pills: [
        quest.quest_type || "quest",
        quest.status || "active",
        `${Number(quest.reward_xp || 0)} XP`,
        quest.reward_chest_type ? quest.reward_chest_type.replaceAll("_", " ") : "no chest",
      ],
    }));
  });
}

function renderInventory() {
  const chestList = $("[data-chest-list]");
  const itemList = $("[data-item-list]");
  const chests = state.inventory.chests || [];
  const items = state.inventory.items || [];

  chestList.replaceChildren();
  itemList.replaceChildren();
  setText("[data-chest-count]", chests.length.toString());
  setText("[data-item-count]", items.length.toString());

  if (!chests.length) {
    chestList.append(createListItem({
      title: "No chests yet",
      description: "The First Road grants the first one.",
      pills: ["earned only"],
    }));
  } else {
    chests.forEach((chest) => {
      chestList.append(createListItem({
        title: chest.name,
        description: chest.description,
        pills: [chest.rarity, chest.opened_at ? "opened" : "sealed"],
      }));
    });
  }

  if (!items.length) {
    itemList.append(createListItem({
      title: "No items equipped",
      description: "Chests can grant cosmetics, titles, and frames.",
      pills: ["private profile"],
    }));
  } else {
    items.forEach((item) => {
      itemList.append(createListItem({
        title: item.name,
        description: item.description,
        pills: [item.rarity, item.item_type, item.is_equipped ? "equipped" : "stored"],
      }));
    });
  }
}

function renderSkills() {
  const list = $("[data-skill-list]");
  const skills = state.skills || demo.skills;
  const unlocked = new Set((skills.unlocked_nodes || []).map((node) => Number(node.node_id)));

  list.replaceChildren();
  setText("[data-skill-summary]", Number(skills.available_skill_points || 0).toString());

  (skills.nodes || []).forEach((node) => {
    list.append(createListItem({
      title: node.name,
      description: node.description,
      pills: [
        node.branch,
        `${Number(node.cost || 1)} point`,
        unlocked.has(Number(node.id)) ? "unlocked" : "locked",
      ],
    }));
  });
}

function renderAuth() {
  const authState = $("[data-auth-state]");
  const forms = $$("[data-auth-form]");
  const logout = $("[data-logout]");

  authState.classList.toggle("is-online", state.authenticated);
  authState.querySelector("strong").textContent = state.authenticated ? "Account online" : "Demo state";
  forms.forEach((form) => form.classList.toggle("is-hidden", state.authenticated));
  logout.classList.toggle("is-hidden", !state.authenticated);
}

function renderAll() {
  renderDashboard();
  renderRegions();
  renderQuests();
  renderInventory();
  renderSkills();
  renderAuth();
}

async function loadAuthenticatedData() {
  const [dashboard, regions, quests, inventory, skills, strava] = await Promise.all([
    api("../api/game/dashboard.php"),
    api("../api/world/regions.php"),
    api("../api/quests/active.php"),
    api("../api/inventory/list.php"),
    api("../api/skill-tree.php"),
    api("../api/strava/status.php"),
  ]);

  state.user = dashboard.user;
  state.stats = dashboard.stats || demo.stats;
  state.quests = quests.quests.length ? quests.quests : demo.quests;
  state.regions = regions.regions.length ? regions.regions : demo.regions;
  state.inventory = inventory;
  state.skills = skills;
  state.strava = strava;
}

async function loadSession() {
  try {
    const session = await api("../api/auth/me.php");
    state.csrfToken = session.csrf_token || "";
    state.authenticated = Boolean(session.authenticated);

    if (state.authenticated) {
      state.user = session.user;
      await loadAuthenticatedData();
      setStatus("Account online. Trailbound data is live.", true);
    } else {
      setStatus("Demo state active. Register or log in when the database is configured.");
    }
  } catch (error) {
    state.setupMessage = error.message;
    setStatus(error.message);
  }

  renderAll();
  showStravaRedirectStatus();
}

function bindNavigation() {
  $$("[data-view]").forEach((button) => {
    button.addEventListener("click", () => {
      $$("[data-view]").forEach((item) => item.classList.toggle("is-active", item === button));
      $$("[data-panel]").forEach((panel) => panel.classList.toggle("is-active", panel.dataset.panel === button.dataset.view));
    });
  });
}

function bindAuthTabs() {
  $$("[data-auth-mode]").forEach((button) => {
    button.addEventListener("click", () => {
      const mode = button.dataset.authMode;
      $$("[data-auth-mode]").forEach((tab) => tab.classList.toggle("is-active", tab === button));
      $$("[data-auth-form]").forEach((form) => form.classList.toggle("is-hidden", form.dataset.authForm !== mode));
    });
  });
}

function formPayload(form) {
  return Object.fromEntries(new FormData(form).entries());
}

function bindAuthForms() {
  $$("[data-auth-form]").forEach((form) => {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const mode = form.dataset.authForm;
      const endpoint = mode === "register" ? "../api/auth/register.php" : "../api/auth/login.php";
      const submit = form.querySelector("button[type='submit']");

      submit.disabled = true;

      try {
        const data = await api(endpoint, {
          method: "POST",
          body: JSON.stringify(formPayload(form)),
        });
        state.authenticated = true;
        state.csrfToken = data.csrf_token || state.csrfToken;
        state.user = data.user;
        await loadAuthenticatedData();
        setStatus("Account online. Trailbound data is live.", true);
        showToast(mode === "register" ? "Account created." : "Logged in.");
        form.reset();
      } catch (error) {
        showToast(error.message);
      } finally {
        submit.disabled = false;
        renderAll();
      }
    });
  });

  $("[data-logout]").addEventListener("click", async () => {
    try {
      await api("../api/auth/logout.php", { method: "POST", body: "{}" });
      state.authenticated = false;
      state.user = demo.user;
      state.stats = demo.stats;
      state.quests = demo.quests;
      state.regions = demo.regions;
      state.inventory = demo.inventory;
      state.skills = demo.skills;
      setStatus("Demo state active. Register or log in when the database is configured.");
      showToast("Logged out.");
    } catch (error) {
      showToast(error.message);
    } finally {
      renderAll();
    }
  });
}

function bindActions() {
  const connect = () => {
    if (!state.authenticated) {
      showToast("Log in before connecting Strava.");
      return;
    }

    window.location.href = "../api/strava/connect.php";
  };

  $("[data-connect-strava]").addEventListener("click", connect);
  $("[data-connect-strava-secondary]").addEventListener("click", connect);

  $("[data-sync-runs]").addEventListener("click", async () => {
    if (!state.authenticated) {
      showToast("Log in before syncing runs.");
      return;
    }

    if (!state.strava.connected) {
      showToast("Connect Strava before syncing runs.");
      return;
    }

    const button = $("[data-sync-runs]");
    button.disabled = true;
    button.textContent = "Syncing";

    try {
      const result = await api("../api/strava/sync.php", {
        method: "POST",
        body: "{}",
      });
      await loadAuthenticatedData();
      renderAll();

      const imported = Number(result.imported_count || 0);
      showToast(imported ? `${imported} run synced. Rewards processed.` : "No new valid runs found.");
    } catch (error) {
      showToast(error.message);
    } finally {
      button.disabled = false;
      button.textContent = "Sync Runs";
    }
  });

  $$("[data-class]").forEach((button) => {
    button.addEventListener("click", async () => {
      const avatarClass = button.dataset.class;

      if (!state.authenticated) {
        state.user = { ...state.user, avatar_class: avatarClass };
        showToast(`${classNames[avatarClass]} selected for demo.`);
        renderAll();
        return;
      }

      try {
        const data = await api("../api/onboarding/choose-class.php", {
          method: "POST",
          body: JSON.stringify({ avatar_class: avatarClass }),
        });
        state.user = data.user;
        showToast(`${classNames[avatarClass]} selected.`);
      } catch (error) {
        showToast(error.message);
      } finally {
        renderAll();
      }
    });
  });
}

function showStravaRedirectStatus() {
  const params = new URLSearchParams(window.location.search);
  const status = params.get("strava");

  if (!status) {
    return;
  }

  const messages = {
    connected: "Strava connected.",
    denied: "Strava connection was denied.",
    state_error: "Strava security check failed.",
    athlete_error: "Strava athlete profile was not returned.",
    athlete_linked: "That Strava athlete is already linked.",
  };

  showToast(messages[status] || "Strava connection returned.");
  window.history.replaceState({}, document.title, window.location.pathname);
}

function init() {
  bindNavigation();
  bindAuthTabs();
  bindAuthForms();
  bindActions();
  renderAll();
  loadSession();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
  init();
}
