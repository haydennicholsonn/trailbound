import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Activity, ActivitySquare, BarChart3, Bell, BookOpen, Cable, Camera, CheckCircle2, Compass, Copy, Crosshair, Droplet, Eye as EyeIcon, EyeOff, Gauge, Gem, Globe, Lock, LogOut, Map, MapPin, Maximize2, MessageCircle, Moon, MoreVertical, Navigation, Package as PackageIcon, RadioTower, Search, Share2, Shield, ShoppingBag, Smile, Sparkles, SquarePlus, Star, Sun, Sword, Timer, Trophy, UserCheck, UserPlus, UserRound, Users, X, Zap } from 'lucide-react';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import '../css/app.css';

const nav = [['Dashboard', Gauge], ['Cape Town', Map], ['Progress', BarChart3], ['Tasks', Compass], ['Runs', Activity], ['Social', ActivitySquare], ['Messages', MessageCircle], ['Shop', ShoppingBag], ['Inventory', Gem], ['Skill Tree', Sword], ['Challenges', Trophy], ['Help', BookOpen], ['Profile', UserRound], ['Settings', Shield]];
const palettes = [
  { id: 'trailbound', label: 'Trailbound', swatch: ['#d9a566', '#7dd3a8'] },
  { id: 'blue', label: 'Blue', swatch: ['#60a5fa', '#67e8f9'] },
  { id: 'red', label: 'Red', swatch: ['#fb7185', '#f59e0b'] },
  { id: 'green', label: 'Green', swatch: ['#34d399', '#a3e635'] },
  { id: 'rainbow', label: 'Rainbow', swatch: ['#60a5fa', '#f472b6', '#facc15'] },
  { id: 'pink', label: 'Pink', swatch: ['#f472b6', '#f9a8d4'] },
  { id: 'purple', label: 'Purple', swatch: ['#a78bfa', '#60a5fa'] },
];
const runnerClasses = [
  { id: 'Pathfinder', icon: Compass, title: 'Pathfinder', copy: 'Balanced exploration and questing.', play: 'Best first class if you want the whole Trailbound loop.', strengths: ['Map discovery', 'Quest tempo', 'Flexible growth'], start: 'Centre of the skill tree' },
  { id: 'Sprinter', icon: Zap, title: 'Sprinter', copy: 'Speed-focused progression.', play: 'For short, sharp efforts and pace-driven goals.', strengths: ['Pace rewards', 'Fast dailies', 'Burst challenges'], start: 'Speed branch' },
  { id: 'Endurer', icon: Timer, title: 'Endurer', copy: 'Distance and consistency focused.', play: 'For steady mileage, streaks, and long-form progress.', strengths: ['Weekly goals', 'Streaks', 'Distance quests'], start: 'Endurance branch' },
  { id: 'Wanderer', icon: Map, title: 'Wanderer', copy: 'Exploration and discovery focused.', play: 'For runners who want to reveal every shard.', strengths: ['Region unlocks', 'Route variety', 'Discovery XP'], start: 'Exploration branch' },
  { id: 'Strategist', icon: Sword, title: 'Strategist', copy: 'Challenge and reward optimization.', play: 'For players who want efficient XP, Tears, and quests.', strengths: ['Rewards', 'Challenges', 'Skill planning'], start: 'Tactics branch' },
];
const iconMap = { Activity, BarChart3, BookOpen, Compass, Eye: EyeIcon, Gauge, Map, MapPin, MessageCircle, Navigation, RadioTower, Shield, Sparkles, Star, Timer, Trophy, Users, Zap };

async function api(path, options = {}) {
  let body = options.body; let hdrs = { Accept: 'application/json', ...(options.headers || {}) };
  if (!(body instanceof FormData)) { hdrs['Content-Type'] = 'application/json'; body = options.body ? JSON.stringify(options.body) : undefined; }
  const res = await fetch(path, { credentials: 'include', headers: hdrs, ...options, body });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.message || 'Request failed');
  return data;
}

function useParticles(canvasRef) {
  useEffect(() => {
    const c = canvasRef.current; if (!c) return; const ctx = c.getContext('2d');
    const pts = []; const cols = ['#38bdf8', '#34d399', '#facc15', '#fb7185']; let w = 0, h = 0, raf = 0;
    const rs = () => { const dpr = Math.min(devicePixelRatio || 1, 2); w = innerWidth; h = innerHeight; c.width = w * dpr; c.height = h * dpr; c.style.width = `${w}px`; c.style.height = `${h}px`; ctx.setTransform(dpr, 0, 0, dpr, 0, 0); };
    const brst = (x, y, n = 3) => { for (let i = 0; i < n; i++) pts.push({ x, y, vx: (Math.random() - 0.5) * 1.4, vy: (Math.random() - 0.5) * 1.4, life: 1, sz: 2 + Math.random() * 4, cl: cols[~~(Math.random() * cols.length)] }); if (pts.length > 150) pts.splice(0, pts.length - 150); };
    const rd = () => { ctx.clearRect(0, 0, w, h); for (let i = pts.length - 1; i >= 0; i--) { const p = pts[i]; p.x += p.vx; p.y += p.vy; p.life -= 0.024; if (p.life <= 0) { pts.splice(i, 1); continue; } ctx.globalAlpha = p.life * 0.75; ctx.fillStyle = p.cl; ctx.shadowBlur = 18; ctx.shadowColor = p.cl; ctx.beginPath(); ctx.arc(p.x, p.y, p.sz * p.life, 0, Math.PI * 2); ctx.fill(); } ctx.globalAlpha = 1; ctx.shadowBlur = 0; raf = requestAnimationFrame(rd); };
    rs(); rd();
    const mv = (e) => brst(e.clientX, e.clientY, e.pointerType === 'touch' ? 5 : 2);
    const sc = () => brst(innerWidth * (0.25 + Math.random() * 0.5), innerHeight - 84, 5);
    addEventListener('resize', rs, { passive: true }); addEventListener('pointermove', mv, { passive: true }); addEventListener('scroll', sc, { passive: true });
    return () => { cancelAnimationFrame(raf); removeEventListener('resize', rs); removeEventListener('pointermove', mv); removeEventListener('scroll', sc); };
  }, [canvasRef]);
}

function useRealtime(onEvent) {
  const onEventRef = useRef(onEvent);
  const [connected, setConnected] = useState(false);
  useEffect(() => { onEventRef.current = onEvent; }, [onEvent]);
  useEffect(() => {
    let ws = null;
    let closed = false;
    let retry = 900;
    let timer = null;
    const connect = () => {
      const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
      ws = new WebSocket(`${protocol}//${location.host}/ws`);
      ws.onopen = () => { retry = 900; setConnected(true); };
      ws.onclose = () => {
        setConnected(false);
        if (!closed) {
          timer = setTimeout(connect, retry);
          retry = Math.min(8000, retry * 1.7);
        }
      };
      ws.onerror = () => ws?.close();
      ws.onmessage = (event) => {
        try {
          onEventRef.current?.(JSON.parse(event.data));
        } catch {
          onEventRef.current?.({ type: 'refresh' });
        }
      };
    };
    connect();
    return () => { closed = true; clearTimeout(timer); ws?.close(); };
  }, []);
  return connected;
}

/* ─── Eye brand mark ─── */
function Eye({ small, phrase, onCharm }) {
  const [blink, setBlink] = useState(false);
  const [look, setLook] = useState({ x: 0, y: 0 });
  const [fond, setFond] = useState(false);
  useEffect(() => {
    let t;
    const clamp = (n) => Math.max(-1, Math.min(1, n));
    const sched = () => { t = setTimeout(() => { setBlink(true); setTimeout(() => setBlink(false), 170); sched(); }, 2400 + Math.random() * 5200); };
    const mv = (e) => setLook({ x: clamp((e.clientX / innerWidth - 0.5) * 2), y: clamp((e.clientY / innerHeight - 0.5) * 2) });
    const sc = () => setLook({ x: Math.sin(scrollY / 300) * 0.7, y: clamp(scrollY / 520 - 0.5) });
    addEventListener('pointermove', mv, { passive: true }); addEventListener('scroll', sc, { passive: true }); sched();
    return () => { clearTimeout(t); removeEventListener('pointermove', mv); removeEventListener('scroll', sc); };
  }, []);
  const charm = () => {
    if (small) return;
    setFond(true);
    setBlink(true);
    onCharm?.();
    setTimeout(() => setBlink(false), 150);
    setTimeout(() => setFond(false), 760);
  };
  const Shell = small ? 'span' : 'button';
  const shellProps = small ? { 'aria-hidden': 'true' } : { type: 'button', onClick: charm, 'aria-label': 'Orrin, your Trailbound watcher' };
  return (
    <Shell className={`eye${blink ? ' blink' : ''}${fond ? ' fond' : ''}${small ? ' eyeSm' : ''}`} {...shellProps}>
      {!small && <span className="eyeName">Orrin</span>}
      <svg viewBox="0 0 140 96">
        <defs>
          <linearGradient id="shellGrad" x1="8" y1="18" x2="132" y2="78">
            <stop offset="0" stopColor="var(--accent-secondary)" /><stop offset="0.5" stopColor="var(--accent)" /><stop offset="1" stopColor="var(--accent-strong)" />
          </linearGradient>
          <radialGradient id="irisGrad" cx="50%" cy="50%" r="60%">
            <stop offset="0" stopColor="#f8fafc" /><stop offset="0.18" stopColor="var(--accent-strong)" /><stop offset="0.56" stopColor="var(--accent)" /><stop offset="1" stopColor="#020611" />
          </radialGradient>
        </defs>
        <path className="eyeHalo" d="M10 48C25 20 47 9 70 9s45 11 60 39c-15 28-37 39-60 39S25 76 10 48Z" />
        <path className="eyeAura" d="M13 48C28 22 49 12 70 12s42 10 57 36c-15 26-36 36-57 36S28 74 13 48Z" />
        <path className="eyeInner" d="M22 48c13-19 29-28 48-28s35 9 48 28c-13 19-29 28-48 28S35 67 22 48Z" />
        <path className="eyeWet" d="M27 47c14-15 28-22 43-22s29 7 43 22" />
        <g className="eyeLook" style={{ transform: `translate(${look.x * 9}px, ${look.y * 6}px)` }}>
          <circle className="irisOuter" cx="70" cy="48" r="22" />
          <circle className="iris" cx="70" cy="48" r="17" />
          <circle className="irisRing" cx="70" cy="48" r="11" />
          <circle className="pupil" cx="70" cy="48" r="7.5" />
          <circle className="spark" cx="63" cy="39" r="3.2" />
          <circle className="spark tiny" cx="76" cy="37" r="1.2" />
        </g>
        <path className="eyeSmile" d="M58 76c4 4 20 4 24 0" />
        <path className="eyeShell" d="M13 48C28 22 49 12 70 12s42 10 57 36c-15 26-36 36-57 36S28 74 13 48Z" />
      </svg>
      {!small && phrase && <span className="eyeSpeech">{phrase}</span>}
    </Shell>
  );
}

function BrandLogo({ compact = false }) {
  return <div className={`brandLogo${compact ? ' compact' : ''}`}><Eye small /><div><small>Project</small><strong>Trailbound</strong></div></div>;
}

function AuthGate({ onAuthed }) {
  const [mode, setMode] = useState('register');
  const [form, setForm] = useState({ name: '', email: '', password: '', runner_type: 'Pathfinder', weekly_goal_km: 15, referral_code: '', package_id: '', lat: null, lng: null, accuracy_m: null });
  const [packages, setPackages] = useState([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [geoState, setGeoState] = useState({ status: 'idle', message: 'Trailbound starts you where you actually are. Your starting shard is based on your current location.', region: null });
  useEffect(() => {
    api('/api/packages').then(d => {
      setPackages(d.packages || []);
      const def = (d.packages || []).find(pkg => pkg.is_default) || d.packages?.[0];
      if (def) setForm(current => current.package_id ? current : { ...current, package_id: def.id });
    }).catch(() => setPackages([]));
  }, []);
  useEffect(() => { if (!error) return; const id = setTimeout(() => setError(''), 5200); return () => clearTimeout(id); }, [error]);
  const passwordHint = useMemo(() => {
    if (!form.password || mode !== 'register') return '';
    if (form.password.length < 10) return `Add ${10 - form.password.length} more character${10 - form.password.length === 1 ? '' : 's'} to your password.`;
    if (!/[a-z]/i.test(form.password)) return 'Password needs at least one letter.';
    if (!/\d/.test(form.password)) return 'Password needs at least one number.';
    return 'Password looks ready.';
  }, [form.password, mode]);
  const detectShard = () => {
    setError('');
    if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
      setGeoState({ status: 'error', message: 'Location needs HTTPS. Open Trailbound on the secure domain and try again.', region: null });
      return;
    }
    if (!navigator.geolocation) {
      setGeoState({ status: 'error', message: 'This browser does not support location access.', region: null });
      return;
    }
    setGeoState({ status: 'loading', message: 'Asking your browser for your current location...', region: null });
    navigator.geolocation.getCurrentPosition(async (pos) => {
      const coords = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy_m: Math.round(pos.coords.accuracy || 0),
      };
      setForm(current => ({ ...current, ...coords }));
      try {
        const d = await api('/api/auth/detect-shard', { method: 'POST', body: coords });
        setGeoState({ status: d.region ? 'success' : 'error', message: d.message, region: d.region });
      } catch (err) {
        setGeoState({ status: 'error', message: err.message, region: null });
      }
    }, (err) => {
      const message = err.code === err.PERMISSION_DENIED
        ? 'Location permission was blocked. Enable location for this site, then retry.'
        : err.code === err.TIMEOUT
          ? 'Location timed out. Try again somewhere with a clearer GPS signal.'
          : 'Location was unavailable. Please retry.';
      setGeoState({ status: 'error', message, region: null });
    }, { enableHighAccuracy: true, maximumAge: 0, timeout: 18000 });
  };
  const submit = async (e) => {
    e.preventDefault();
    setError('');
    if (mode === 'register' && !geoState.region) {
      setError('Detect your starting shard before creating your account.');
      return;
    }
    setLoading(true);
    try {
      const payload = mode === 'register' ? form : { email: form.email, password: form.password };
      const d = await api(`/api/auth/${mode}`, { method: 'POST', body: payload });
      onAuthed(d.user);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };
  return (
    <main className="authScreen">
      <section className="authHero">
        <div className="authHeroCopy">
          <p className="kicker">Cape Town alpha</p>
          <h1>{mode === 'login' ? 'Return to the shard.' : 'Run the city like a living game.'}</h1>
          <p>{mode === 'login' ? 'Pick up your quests, check your friends, and see what Orrin noticed while you were away.' : 'Trailbound turns real Cape Town runs into map discovery, quests, XP, Tears, badges, and social moments worth showing off.'}</p>
          <div className="authProof"><span>18 real shards</span><span>Quest rewards</span><span>Live friends</span></div>
          <div className="authSignalGrid">
            <div><b>2 km</b><span>starter quest</span></div>
            <div><b>+1</b><span>skill point per level</span></div>
            <div><b>#trailboundapp</b><span>share-ready</span></div>
          </div>
        </div>
        <div className="authEyeStage"><Eye /><span>Orrin is watching your route wake up.</span></div>
      </section>
      <form className="authPanel" onSubmit={submit}>
        <div className="authPanelHead"><BrandLogo compact /><p>{mode === 'login' ? 'Sign in and get back to your current zone.' : 'Create your runner, detect your starting shard, and choose your first path.'}</p></div>
        <div className="tabs"><button type="button" className={mode === 'register' ? 'active' : ''} onClick={() => setMode('register')}>Register</button><button type="button" className={mode === 'login' ? 'active' : ''} onClick={() => setMode('login')}>Login</button></div>
        {mode === 'register' && <>
          <label>Name<input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required /></label>
          <div className={`geoCard ${geoState.status}`}>
            <div><Navigation size={18} /><strong>{geoState.region ? geoState.region.name : 'Detect starting shard'}</strong></div>
            <p>{geoState.region ? `${geoState.region.biome} · ${geoState.region.difficulty}. ${geoState.region.summary}` : geoState.message}</p>
            <button type="button" className={geoState.status === 'success' ? 'ghost' : 'primary'} onClick={detectShard} disabled={geoState.status === 'loading'}>
              <MapPin size={15} />{geoState.status === 'loading' ? 'Locating...' : geoState.region ? 'Retry location' : 'Use my location'}
            </button>
          </div>
          <div className="classPick">
            <div className="miniHead"><span>Runner class</span><strong>{form.runner_type}</strong></div>
            {runnerClasses.map(c => {
              const Icon = c.icon;
              return <button key={c.id} type="button" className={`classCard${form.runner_type === c.id ? ' selected' : ''}`} onClick={() => setForm({ ...form, runner_type: c.id })}>
                <Icon size={18} />
                <span><strong>{c.title}</strong><small>{c.copy}</small><em>{c.start}</em></span>
              </button>;
            })}
          </div>
          <div className="split"><label>Weekly goal km<input type="number" min="1" max="250" value={form.weekly_goal_km} onChange={e => setForm({ ...form, weekly_goal_km: Number(e.target.value) })} /></label><label>Referral code <small>Optional friend code or username</small><input value={form.referral_code} onChange={e => setForm({ ...form, referral_code: e.target.value })} placeholder="HAYDEN-123AB" /></label></div>
          {packages.length > 0 && <div className="signupPackages">
            <div className="miniHead"><span>Package</span><strong>{packages.find(pkg => String(pkg.id) === String(form.package_id))?.name || 'Free'}</strong></div>
            {packages.map(pkg => <PackageCard key={pkg.id} pkg={pkg} selected={String(form.package_id) === String(pkg.id)} onSelect={() => setForm({ ...form, package_id: pkg.id })} />)}
          </div>}
        </>}
        <label>Email<input type="email" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} required /></label>
        <label>Password<div className="passwordWrap"><input type={showPassword ? 'text' : 'password'} value={form.password} onChange={e => setForm({ ...form, password: e.target.value })} required /><button type="button" onClick={() => setShowPassword(v => !v)} aria-label={showPassword ? 'Hide password' : 'Show password'}>{showPassword ? <EyeOff size={17} /> : <EyeIcon size={17} />}</button></div>{passwordHint && <small className={passwordHint.includes('ready') ? 'goodHint' : ''}>{passwordHint}</small>}</label>
        {error && <p className="error">{error}</p>}
        <button className="primary" disabled={loading}>{loading ? 'Working...' : mode === 'register' ? 'Create account' : 'Sign in'}</button>
        <button type="button" className="ghost" onClick={() => { window.location.href = '/api/auth/google'; }}>Sign in with Google</button>
      </form>
    </main>
  );
}

function Panel({ title, eyebrow, children }) {
  return <section className="panel"><div className="panelHead"><span>{eyebrow}</span><strong>{title}</strong></div>{children}</section>;
}

function Sparkline({ values = [] }) {
  const clean = values.length ? values : [3, 5, 4, 7, 6, 8, 7];
  const max = Math.max(...clean, 1);
  const points = clean.map((v, i) => `${(i / Math.max(1, clean.length - 1)) * 100},${28 - (v / max) * 22}`).join(' ');
  return <svg className="sparkline" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true"><polyline points={points} /></svg>;
}

function PremiumStatCard({ icon: Icon, value, label, context, progress = 0, tone = 'accent' }) {
  const safeProgress = Math.max(0, Math.min(100, Number(progress) || 0));
  return <article className={`metricCard ${tone}`}>
    <div className="metricTop"><span className="metricIcon">{Icon && <Icon size={16} />}</span><Sparkline values={[2, 3, safeProgress / 16 + 2, safeProgress / 12 + 3, safeProgress / 10 + 2]} /></div>
    <strong>{value}</strong>
    <span>{label}</span>
    <small>{context || 'Awaiting more signal'}</small>
    <div className="metricProgress"><i style={{ width: `${safeProgress}%` }} /></div>
  </article>;
}

function Chip({ children, tone = '' }) {
  return <span className={`chip ${tone}`}>{children}</span>;
}

function AppearancePanel({ theme, setTheme, palette, setPalette }) {
  return <Panel eyebrow="Appearance" title="Interface theme">
    <div className="appearanceStack">
      <div className="themeSwitch">
        <button className={theme === 'dark' ? 'active' : ''} onClick={() => setTheme('dark')} type="button"><Moon size={15} />Dark</button>
        <button className={theme === 'light' ? 'active' : ''} onClick={() => setTheme('light')} type="button"><Sun size={15} />Light</button>
      </div>
      <div className="paletteGrid">
        {palettes.map(item => <button key={item.id} type="button" className={`paletteSwatch${palette === item.id ? ' active' : ''}`} onClick={() => setPalette(item.id)} aria-pressed={palette === item.id}>
          <span className="swatchPreview" style={{ background: item.swatch.length > 2 ? `linear-gradient(90deg,${item.swatch.join(',')})` : `linear-gradient(135deg,${item.swatch[0]},${item.swatch[1]})` }} />
          <span>{item.label}</span>
        </button>)}
      </div>
      <p className="muted">Palette changes update navigation, charts, progress, badges, borders, and key action states across Trailbound.</p>
    </div>
  </Panel>;
}

function EventMixChart({ items = [] }) {
  const total = items.reduce((sum, item) => sum + Number(item.total || 0), 0);
  const ranked = [...items].sort((a, b) => b.total - a.total);
  const top = ranked[0];
  if (!total) return <div className="analyticsEmpty"><ActivitySquare size={24} /><strong>No event signal yet</strong><p>Activity distribution will appear once players start posting, running, messaging, and unlocking regions.</p></div>;
  let offset = 0;
  const segments = ranked.map(item => {
    const pct = (item.total / total) * 100;
    const segment = <i key={item.type} style={{ left: `${offset}%`, width: `${pct}%` }} title={`${item.type}: ${item.total}`} />;
    offset += pct;
    return segment;
  });
  return <div className="eventMix">
    <div className="eventSummary">
      <div><b>{total}</b><span>Total events</span></div>
      <div><b>{top?.type?.replaceAll('_', ' ') || 'None'}</b><span>Top event</span></div>
      <div><b>{ranked.length}</b><span>Event types</span></div>
    </div>
    <div className="eventStack" aria-label="Event mix stacked bar">{segments}</div>
    <div className="eventRows">
      {ranked.map(item => {
        const pct = total ? (item.total / total) * 100 : 0;
        return <div key={item.type} className="eventRow">
          <div><strong>{item.type.replaceAll('_', ' ')}</strong><small>{item.total} events</small></div>
          <div className="eventTrack"><i style={{ width: `${pct}%` }} /></div>
          <b>{pct.toFixed(pct >= 10 ? 0 : 1)}%</b>
        </div>;
      })}
    </div>
  </div>;
}

/* ─── Profile Modal ─── */
function ProfileModal({ user, onlineIds, onClose, refreshKey }) {
  const [statuses, setStatuses] = useState(null);
  useEffect(() => { api('/api/status').then(d => setStatuses(d.status)).catch(() => setStatuses(null)); }, [refreshKey]);
  const isOnline = true;
  const profile = user.profile || {};
  const stats = user.stats || {};

  return (
    <div className="modalBackdrop" onClick={onClose}>
      <div className="modalContent" onClick={e => e.stopPropagation()}>
        <button className="modalClose" onClick={onClose}><X size={20} /></button>
        <div className="modalHero">
          <div className="modalAvatar">
            {profile.avatar_path ? <img src={profile.avatar_path} alt="" /> : <UserRound size={48} />}
            {isOnline && <span className="onlineDot onlineBig" />}
          </div>
          <div>
            <h2>{profile.display_name || user.name}</h2>
            <div className="modalMeta">
              <span className="tag">{profile.runner_type || 'Runner'}</span>
              <span className="tag">Lvl {stats.level}</span>
              <span className="tag">{profile.home_area || 'Cape Town'}</span>
            </div>
            <p className="modalStatus">
              <span className={`onlineInd ${isOnline ? 'on' : 'off'}`} />
              {isOnline ? 'Online now' : 'Offline'}
            </p>
          </div>
        </div>
        <div className="modalGrid">
          <div className="modalStat"><b>{stats.xp}</b><span>XP</span></div>
          <div className="modalStat"><b>{stats.total_km}</b><span>km</span></div>
          <div className="modalStat"><b>{stats.runs}</b><span>runs</span></div>
          <div className="modalStat"><b>{profile.weekly_goal_km || 0}</b><span>km/wk goal</span></div>
        </div>
        {profile.bio && <div className="modalBio"><p>{profile.bio}</p></div>}
        {statuses && (
          <div className="modalCurrent">
            <Smile size={16} />
            <span>{statuses.status_text}</span>
            {statuses.mood && <span className="moodTag">{statuses.mood}</span>}
            <time>{new Date(statuses.created_at).toLocaleString()}</time>
          </div>
        )}
        <div className="modalPrivacy">
          <span>Privacy: {profile.privacy_level}</span>
        </div>
      </div>
    </div>
  );
}

/* ─── Interactive World Map ─── */
const regionColor = (region) => {
  if (region.status === 'locked') return '#111827';
  if (region.difficulty === 'hard') return '#ff375f';
  if (region.difficulty === 'starter') return '#30d158';
  if ((region.biome || '').includes('coast') || (region.biome || '').includes('reef') || (region.biome || '').includes('dune')) return '#64d2ff';
  if ((region.biome || '').includes('vine') || (region.biome || '').includes('vale') || (region.biome || '').includes('garden')) return '#ffd60a';
  return '#0a84ff';
};

const regionCentroid = (polygon) => {
  const coords = polygon?.coordinates?.[0] || [];
  if (!coords.length) return [18.4241, -33.9249];
  const sum = coords.reduce((acc, [lng, lat]) => [acc[0] + lng, acc[1] + lat], [0, 0]);
  return [sum[0] / coords.length, sum[1] / coords.length];
};

const regionFeatureCollection = (regions) => ({
  type: 'FeatureCollection',
  features: (regions || []).filter(region => region.polygon).map(region => ({
    type: 'Feature',
    geometry: region.polygon,
    properties: {
      id: region.id,
      name: region.name,
      biome: region.biome,
      difficulty: region.difficulty,
      status: region.status,
      progress: region.progress,
      real_name: region.real_name || '',
      run_count: region.run_count || 0,
      distance_km: region.distance_km || 0,
      fill: regionColor(region),
    },
  })),
});

const labelFeatureCollection = (regions) => ({
  type: 'FeatureCollection',
  features: (regions || []).filter(region => region.polygon).map(region => ({
    type: 'Feature',
    geometry: { type: 'Point', coordinates: regionCentroid(region.polygon) },
    properties: {
      id: region.id,
      name: region.name,
      biome: region.biome,
      status: region.status,
      fill: regionColor(region),
    },
  })),
});

const boundaryFeatureCollection = (regions) => {
  const edges = new Map();
  (regions || []).forEach(region => {
    const ring = region.polygon?.coordinates?.[0] || [];
    for (let i = 0; i < ring.length - 1; i++) {
      const a = ring[i];
      const b = ring[i + 1];
      const keyA = `${a[0].toFixed(5)},${a[1].toFixed(5)}`;
      const keyB = `${b[0].toFixed(5)},${b[1].toFixed(5)}`;
      const key = keyA < keyB ? `${keyA}|${keyB}` : `${keyB}|${keyA}`;
      if (!edges.has(key)) edges.set(key, { a, b, count: 0 });
      edges.get(key).count += 1;
    }
  });
  return {
    type: 'FeatureCollection',
    features: [...edges.values()].map(edge => ({
      type: 'Feature',
      geometry: { type: 'LineString', coordinates: [edge.a, edge.b] },
      properties: { shared: edge.count > 1 },
    })),
  };
};

const capeFogFeature = (regions) => ({
  type: 'Feature',
  geometry: {
    type: 'Polygon',
    coordinates: [
      [[18.18, -33.72], [18.82, -33.72], [18.82, -34.23], [18.18, -34.23], [18.18, -33.72]],
      ...(regions || []).filter(region => region.status !== 'locked' && region.polygon?.coordinates?.[0]).map(region => region.polygon.coordinates[0]),
    ],
  },
  properties: {},
});

function WorldMap({ regions, friends, profile, myLocation, friendLocations, beacons, onDropBeacon, onMessageFriend, onLogLocation, locationStatus, locationLoading, realtimeConnected }) {
  const mapContainer = useRef(null);
  const mapRef = useRef(null);
  const popupRef = useRef(null);
  const liveMarkersRef = useRef([]);
  const beaconMarkersRef = useRef([]);
  const questMarkersRef = useRef([]);
  const centeredOnMeRef = useRef(false);
  const [hovered, setHovered] = useState(null);
  const [selectedRegion, setSelectedRegion] = useState(null);
  const [fullScreen, setFullScreen] = useState(false);

  useEffect(() => {
    if (!mapContainer.current || mapRef.current) return;

    const map = new maplibregl.Map({
      container: mapContainer.current,
      style: {
        version: 8,
        sources: { osm: { type: 'raster', tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'], tileSize: 256, attribution: '&copy; OSM' } },
        layers: [
          { id: 'bg', type: 'background', paint: { 'background-color': '#020611' } },
          { id: 'osm-layer', type: 'raster', source: 'osm', paint: { 'raster-brightness-min': 0.08, 'raster-brightness-max': 0.28, 'raster-saturation': -0.35, 'raster-contrast': 0.42, 'raster-opacity': 0.72 } },
        ],
      },
      center: [18.4241, -33.9249], zoom: 11.5, minZoom: 10.2, maxZoom: 16,
      maxBounds: [[18.18, -34.23], [18.82, -33.72]], attributionControl: false,
    });
    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'bottom-right');

    mapRef.current = map;
    return () => { map.remove(); mapRef.current = null; };
  }, []);

  useEffect(() => {
    const map = mapRef.current;
    if (!map || !regions.length) return;

    const drawRegions = () => {
      const atlas = regionFeatureCollection(regions);
      const labels = labelFeatureCollection(regions);
      const boundaries = boundaryFeatureCollection(regions);
      const fog = capeFogFeature(regions);

      if (map.getSource('regions-atlas')) {
        map.getSource('regions-atlas').setData(atlas);
        map.getSource('region-labels').setData(labels);
        map.getSource('region-boundaries').setData(boundaries);
        map.getSource('cape-fog').setData(fog);
        return;
      }

      map.addSource('regions-atlas', { type: 'geojson', data: atlas });
      map.addSource('region-labels', { type: 'geojson', data: labels });
      map.addSource('region-boundaries', { type: 'geojson', data: boundaries });
      map.addSource('cape-fog', { type: 'geojson', data: fog });
      map.addLayer({ id: 'region-fill', type: 'fill', source: 'regions-atlas', paint: { 'fill-color': ['get', 'fill'], 'fill-opacity': ['case', ['==', ['get', 'status'], 'locked'], 0.24, 0.46], 'fill-outline-color': 'transparent' } });
      map.addLayer({ id: 'locked-region-outline', type: 'line', source: 'regions-atlas', paint: { 'line-color': ['case', ['==', ['get', 'status'], 'locked'], 'rgba(148,163,184,.72)', 'rgba(255,255,255,0)'], 'line-width': ['interpolate', ['linear'], ['zoom'], 10, 1.4, 14, 2.4], 'line-opacity': ['case', ['==', ['get', 'status'], 'locked'], 0.72, 0], 'line-dasharray': [2, 2] } });
      map.addLayer({ id: 'region-shine', type: 'line', source: 'regions-atlas', paint: { 'line-color': ['get', 'fill'], 'line-width': ['interpolate', ['linear'], ['zoom'], 10, 4, 14, 10], 'line-opacity': ['case', ['==', ['get', 'status'], 'locked'], 0.12, 0.22], 'line-blur': 8 } });
      map.addLayer({ id: 'region-border-casing', type: 'line', source: 'region-boundaries', paint: { 'line-color': '#020611', 'line-width': ['interpolate', ['linear'], ['zoom'], 10, 3.2, 14, 5.5], 'line-opacity': 0.72 } });
      map.addLayer({ id: 'region-border', type: 'line', source: 'region-boundaries', paint: { 'line-color': ['case', ['get', 'shared'], 'rgba(226,232,240,.58)', 'rgba(148,163,184,.42)'], 'line-width': ['interpolate', ['linear'], ['zoom'], 10, 0.9, 14, 1.8], 'line-opacity': 0.9 } });
      map.addLayer({ id: 'cape-fog', type: 'fill', source: 'cape-fog', paint: { 'fill-color': '#020611', 'fill-opacity': 0.68 } });
      map.addLayer({ id: 'region-label', type: 'symbol', source: 'region-labels', layout: { 'text-field': ['upcase', ['get', 'name']], 'text-font': ['Open Sans Semibold'], 'text-size': ['interpolate', ['linear'], ['zoom'], 10, 9, 13, 12], 'text-letter-spacing': 0.06, 'text-anchor': 'center', 'text-allow-overlap': false }, paint: { 'text-color': ['case', ['==', ['get', 'status'], 'locked'], '#94a3b8', '#f8fafc'], 'text-halo-color': '#020611', 'text-halo-width': 1.6, 'text-opacity': ['interpolate', ['linear'], ['zoom'], 10, 0.45, 12, 0.92] } });

      map.on('mousemove', 'region-fill', (event) => {
        const props = event.features?.[0]?.properties;
        if (!props) return;
        map.getCanvas().style.cursor = 'pointer';
        setHovered({ id: props.id, name: props.name, biome: props.biome, difficulty: props.difficulty, status: props.status, progress: props.progress });
      });
      map.on('click', 'region-fill', (event) => {
        const id = Number(event.features?.[0]?.properties?.id);
        const region = regions.find(item => Number(item.id) === id);
        if (region) setSelectedRegion(region);
      });
      map.on('mouseleave', 'region-fill', () => { map.getCanvas().style.cursor = ''; setHovered(null); });
    };

    if (map.loaded()) drawRegions();
    else map.once('load', drawRegions);
  }, [regions]);

  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;

    liveMarkersRef.current.forEach(marker => marker.remove());
    liveMarkersRef.current = [];

    if (myLocation) {
      const el = document.createElement('div');
      el.className = 'liveMarker self';
      el.innerHTML = '<span class="pulse"></span><span class="core">You</span>';
      liveMarkersRef.current.push(new maplibregl.Marker({ element: el, anchor: 'center' }).setLngLat([myLocation.lng, myLocation.lat]).addTo(map));
    }

    (friendLocations || []).forEach((loc) => {
      const friend = loc.user || {};
      const el = document.createElement('button');
      el.className = 'liveMarker friend';
      el.type = 'button';
      el.innerHTML = friend.avatar_path ? `<img src="${friend.avatar_path}" alt="" /><span class="pulse"></span>` : `<span class="initial">${(friend.display_name || friend.name || '?')[0].toUpperCase()}</span><span class="pulse"></span>`;
      el.title = `${friend.display_name || friend.name} - ${loc.region?.name || 'Cape Town'}`;
      el.addEventListener('click', () => onMessageFriend?.(friend.id));
      liveMarkersRef.current.push(new maplibregl.Marker({ element: el, anchor: 'center' }).setLngLat([loc.lng, loc.lat]).addTo(map));
    });
  }, [myLocation, friendLocations, onMessageFriend]);

  useEffect(() => {
    const map = mapRef.current;
    if (!map || !myLocation || centeredOnMeRef.current) return;
    centeredOnMeRef.current = true;
    map.flyTo({ center: [myLocation.lng, myLocation.lat], zoom: Math.max(map.getZoom(), 12.4), speed: 0.9, curve: 1.2 });
  }, [myLocation]);

  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;

    beaconMarkersRef.current.forEach(marker => marker.remove());
    beaconMarkersRef.current = [];

    (beacons || []).forEach((beacon) => {
      const el = document.createElement('div');
      el.className = `beaconMarker ${beacon.kind}`;
      el.innerHTML = `<span>${beacon.kind === 'challenge' ? '!' : beacon.kind === 'route' ? '↗' : '◆'}</span>`;
      const popup = new maplibregl.Popup({ offset: 18, closeButton: false }).setHTML(`<strong>${beacon.title}</strong><small>${beacon.region?.name || 'Cape Town'} · ${beacon.user?.display_name || beacon.user?.name || 'Runner'}</small>${beacon.note ? `<p>${beacon.note}</p>` : ''}`);
      beaconMarkersRef.current.push(new maplibregl.Marker({ element: el, anchor: 'center' }).setLngLat([beacon.lng, beacon.lat]).setPopup(popup).addTo(map));
    });
  }, [beacons]);

  useEffect(() => {
    const map = mapRef.current;
    if (!map) return;
    questMarkersRef.current.forEach(marker => marker.remove());
    questMarkersRef.current = [];

    regions.forEach(region => {
      if (region.status === 'locked' || !region.polygon) return;
      (region.tasks || []).filter(task => task.status !== 'locked').slice(0, 2).forEach((task, index) => {
        const [lng, lat] = regionCentroid(region.polygon);
        const el = document.createElement('button');
        el.className = `questMarker ${task.status}`;
        el.type = 'button';
        el.innerHTML = `<span>${task.status === 'complete' ? '✓' : '?'}</span>`;
        el.title = `${task.title} - ${region.name}`;
        el.style.transform = `translate(${index * 16 - 8}px, ${index * 12 - 6}px)`;
        el.addEventListener('click', () => window.dispatchEvent(new CustomEvent('trailbound:quest', { detail: { ...task, region: region.name, biome: region.biome, difficulty: region.difficulty } })));
        questMarkersRef.current.push(new maplibregl.Marker({ element: el, anchor: 'center' }).setLngLat([lng, lat]).addTo(map));
      });
    });
  }, [regions]);

  const unlocked = regions.filter(r => r.status !== 'locked');
  const pulseRegions = regions.map(region => ({
    ...region,
    liveCount: (friendLocations || []).filter(loc => loc.region?.id === region.id).length + (myLocation?.region?.id === region.id ? 1 : 0),
    beaconCount: (beacons || []).filter(beacon => beacon.region?.id === region.id).length,
  }));

  return (
    <div className={`mapPanel ${fullScreen ? 'fullScreenMap' : ''}`}>
      <div className="mapWrap">
        <div ref={mapContainer} className="mapContainer" />
        <div className="mapOverlay">
          <span className="mapTitle">CAPE TOWN SHARD</span>
          <span className="mapSub">{unlocked.length} / {regions.length} regions revealed</span>
          <span className="mapCurrent"><Crosshair size={12} />{myLocation?.region?.name || 'Locating territory'}</span>
          <span className={`mapLive ${realtimeConnected ? 'on' : ''}`}><RadioTower size={12} />{realtimeConnected ? 'Live shard link' : 'Reconnecting live link'}</span>
        </div>
        <div className="mapActions">
          <button className="mapActionBtn" onClick={() => setFullScreen(v => !v)}><Maximize2 size={15} />{fullScreen ? 'Exit full map' : 'Full map'}</button>
          <button className="mapActionBtn primaryMap" onClick={onLogLocation} disabled={locationLoading}><Crosshair size={15} />{locationLoading ? 'Logging...' : myLocation ? 'Log current location' : 'Use my location'}</button>
          <button className="mapActionBtn" onClick={() => onDropBeacon?.(mapRef.current?.getCenter())}><RadioTower size={15} /> Drop rally beacon</button>
        </div>
        {locationStatus && <div className="locationPrompt"><MapPin size={15} /><span>{locationStatus}</span></div>}
        {hovered && (
          <div className="mapTooltip">
            <strong>{hovered.name}</strong>
            <small>{hovered.biome} &middot; {hovered.difficulty}</small>
            <span className={`mapTag ${hovered.status === 'locked' ? 'locked' : ''}`}>{hovered.status === 'locked' ? 'LOCKED' : `${hovered.progress}% explored`}</span>
          </div>
        )}
      </div>
      <div className="mapLegend">
        {pulseRegions.map(r => {
          const unl = r.status !== 'locked';
          const clr = !unl ? '#334155' : r.difficulty === 'hard' ? '#fb315f' : r.difficulty === 'starter' ? '#34d399' : '#38bdf8';
          return (
            <button key={r.id} className={`mapRegionItem ${r.status}`} onClick={() => setSelectedRegion(r)} type="button">
              <span className="mrDot" style={{ background: clr, boxShadow: unl ? `0 0 8px ${clr}88` : 'none' }} />
              <div className="mrInfo"><strong>{r.name}</strong><small>{r.real_name || r.biome} &middot; {r.difficulty}</small></div>
              {(r.liveCount > 0 || r.beaconCount > 0) && <div className="pulseStack"><span><Crosshair size={11} />{r.liveCount}</span><span><RadioTower size={11} />{r.beaconCount}</span></div>}
              {unl && <div className="mrProgress"><span>{r.progress}%</span><div className="mrBar"><i style={{ width: `${r.progress}%`, background: clr }} /></div></div>}
              {!unl && <Lock size={13} className="mrLock" />}
            </button>
          );
        })}
      </div>
      {selectedRegion && <ShardModal region={selectedRegion} onClose={() => setSelectedRegion(null)} />}
    </div>
  );
}

/* ─── Feed ─── */
function ShardModal({ region, onClose }) {
  const activeTasks = (region.tasks || []).filter(task => task.status !== 'locked');
  return <div className="modalBackdrop" onClick={onClose}>
    <div className="modalContent shardModal" onClick={e => e.stopPropagation()}>
      <button className="modalClose" onClick={onClose}><X size={20} /></button>
      <span className="kicker">{region.status === 'locked' ? 'Fogged shard' : 'Discovered shard'}</span>
      <div className="shardModalHead">
        <div><h2>{region.name}</h2><p>{region.real_name || 'Cape Town region'}</p></div>
        <Chip tone={region.status === 'locked' ? 'quiet' : 'good'}>{region.status}</Chip>
      </div>
      <p className="muted">{region.summary}</p>
      <div className="questDetailGrid">
        <div><b>{region.progress || 0}%</b><span>explored</span></div>
        <div><b>{region.run_count || 0}</b><span>runs</span></div>
        <div><b>{region.distance_km || 0} km</b><span>distance</span></div>
        <div><b>{region.biome}</b><span>biome</span></div>
      </div>
      {region.facts?.length > 0 && <div className="shardLore">{region.facts.map((fact, index) => <p key={index}>{fact}</p>)}</div>}
      <div className="shardQuestList">
        <strong>Quest chain</strong>
        {activeTasks.length === 0 ? <small className="muted">Unlock this shard to reveal its quest chain.</small> : activeTasks.map(task => <button key={task.id} onClick={() => window.dispatchEvent(new CustomEvent('trailbound:quest', { detail: { ...task, region: region.name, biome: region.biome, difficulty: region.difficulty } }))}>
          <span>{task.title}</span><small>{task.unlock_rule} - +{task.reward_xp} XP</small><Chip>{task.status}</Chip>
        </button>)}
      </div>
    </div>
  </div>;
}

function RunDashboardModal({ run, loading, onClose }) {
  const paceValues = run?.chart?.pace || [];
  const maxPace = Math.max(...paceValues, 1);
  const minPace = Math.min(...paceValues, maxPace);
  return <div className="modalBackdrop" onClick={onClose}>
    <div className="modalContent runModal" onClick={e => e.stopPropagation()}>
      <button className="modalClose" onClick={onClose}><X size={20} /></button>
      {loading || !run ? <p className="muted">Loading run dashboard...</p> : <>
        <div className="runHero">
          <div><span className="kicker">Run dashboard</span><h2>{run.user.display_name || run.user.name}</h2><p>{run.region?.name || 'Cape Town'} - {new Date(run.run_at).toLocaleString()}</p></div>
          <strong>{run.distance_km} km</strong>
        </div>
        <div className="runStatsGrid">
          <div><b>{run.pace_label}</b><span>avg pace</span></div>
          <div><b>{run.duration_minutes} min</b><span>duration</span></div>
          <div><b>{run.speed_kmh} km/h</b><span>speed</span></div>
          <div><b>+{run.xp_awarded}</b><span>XP</span></div>
        </div>
        <div className="paceChart">
          <div className="chartHead"><strong>Pace by km</strong><small>lower is faster</small></div>
          <div className="chartBars">{paceValues.map((pace, index) => {
            const height = 34 + ((maxPace - pace) / Math.max(0.1, maxPace - minPace || 1)) * 86;
            return <div key={index} className="chartBar"><i style={{ height }} /><span>{run.chart.labels[index]}</span><small>{Math.floor(pace)}:{String(Math.round((pace % 1) * 60)).padStart(2, '0')}</small></div>;
          })}</div>
        </div>
        <div className="splitTable">{run.splits.map(split => <div key={split.km}><span>K{split.km}</span><b>{Math.floor(split.pace_min_km)}:{String(Math.round((split.pace_min_km % 1) * 60)).padStart(2, '0')} /km</b><small>{split.distance_km} km</small></div>)}</div>
        {run.quest_unlocks?.length > 0 && <div className="questUnlocks"><strong>Quest impact</strong>{run.quest_unlocks.map(q => <span key={q.id} className={q.unlocked_by_run ? 'done' : ''}>{q.title} {q.unlocked_by_run ? 'unlocked' : `${q.target_value}km target`}</span>)}</div>}
      </>}
    </div>
  </div>;
}

function QuestModal({ quest, onClose }) {
  if (!quest) return null;
  return <div className="modalBackdrop" onClick={onClose}>
    <div className="modalContent questModal" onClick={e => e.stopPropagation()}>
      <button className="modalClose" onClick={onClose}><X size={20} /></button>
      <span className="kicker">{quest.region || 'Cape Town'} quest</span>
      <h2>{quest.title}</h2>
      <p className="muted">{quest.description}</p>
      <div className="questDetailGrid">
        <div><b>{quest.unlock_rule}</b><span>unlock rule</span></div>
        <div><b>+{quest.reward_xp}</b><span>reward XP</span></div>
        <div><b>{quest.status}</b><span>status</span></div>
        <div><b>{quest.biome || 'Shard'}</b><span>biome</span></div>
      </div>
      <p className="questHint">Log runs in this region to push the quest forward. Completed quest chains reveal more of the Cape Town shard.</p>
    </div>
  </div>;
}

function RunLogModal({ run, setRun, world, fileInputRef, setRunImageFiles, onSubmit, onClose }) {
  return <div className="modalBackdrop" onClick={onClose}>
    <div className="modalContent runLogModal" onClick={e => e.stopPropagation()}>
      <button className="modalClose" onClick={onClose}><X size={20} /></button>
      <span className="kicker">Record effort</span>
      <h2>Log a run</h2>
      <p className="muted">Add the effort, pick the region, and Trailbound will push your shard progress forward.</p>
      <form className="form" onSubmit={onSubmit}>
        <div className="split">
          <label>Distance (km)<input type="number" min="0.5" max="100" step="0.1" value={run.distance_km} onChange={e => setRun({ ...run, distance_km: e.target.value })} required /></label>
          <label>Duration (min)<input type="number" min="3" max="900" step="1" value={run.duration_minutes} onChange={e => setRun({ ...run, duration_minutes: e.target.value })} required /></label>
        </div>
        <label>Region<select value={run.region_id} onChange={e => setRun({ ...run, region_id: e.target.value })} required><option value="">Select region...</option>{world.regions.filter(r => r.status !== 'locked').map(r => <option key={r.id} value={r.id}>{r.name} ({r.biome})</option>)}</select></label>
        <label>Photos<small className="muted">optional run proof</small><input type="file" accept="image/*" multiple ref={fileInputRef} onChange={e => setRunImageFiles(e.target.files)} /></label>
        <button className="primary" type="submit">Save run</button>
      </form>
    </div>
  </div>;
}

function RunRouteMap({ runs, regions }) {
  const plotted = (runs || []).slice(0, 12).map(run => {
    const region = (regions || []).find(item => Number(item.id) === Number(run.region_id));
    const [lng, lat] = region?.polygon ? regionCentroid(region.polygon) : [18.4241, -33.9249];
    return { ...run, lng, lat, region };
  });
  const x = (lng) => ((lng - 18.18) / (18.82 - 18.18)) * 100;
  const y = (lat) => ((-33.72 - lat) / (-33.72 + 34.23)) * 100;
  return <div className="runRouteMap">
    <div className="runRouteHead"><span className="kicker">Route memory</span><strong>{plotted.length} recent signals</strong></div>
    <svg viewBox="0 0 100 100" role="img" aria-label="Recent run map">
      {(regions || []).filter(region => region.polygon).map(region => {
        const ring = region.polygon.coordinates?.[0] || [];
        const points = ring.map(([lng, lat]) => `${x(lng)},${y(lat)}`).join(' ');
        return <polygon key={region.id} points={points} className={region.status === 'locked' ? 'locked' : 'open'} />;
      })}
      {plotted.map((run, index) => <g key={run.id} transform={`translate(${x(run.lng)} ${y(run.lat)})`}>
        <circle r={4 + Math.min(5, Number(run.distance_km || 0) / 2)} className="runPulse" />
        <circle r="2.2" className="runPin" />
        <text x="5" y="-4">{Number(run.distance_km).toFixed(1)}km</text>
      </g>)}
    </svg>
    <p className="muted">Markers scale with distance and sit inside the shard where each run was logged.</p>
  </div>;
}

function ProfileHeroCard({ user, onAvatar, onBackground }) {
  const profile = user.profile || {};
  return <section className="profileHeroCard">
    <div className="profileCover" style={profile.background_path ? { backgroundImage: `url(${profile.background_path}?v=${profile.updated_at || user.updated_at || '1'})` } : undefined}>
      <label className="coverUpload"><Camera size={14} /> Cover<input type="file" accept="image/*" onChange={e => { if (e.target.files[0]) onBackground(e.target.files[0]); }} hidden /></label>
    </div>
    <div className="profileHeroBody">
      <label className="profileBigAvatar">
        {profile.avatar_path ? <img src={profile.avatar_path} alt="" /> : <UserRound size={56} />}
        <span><Camera size={14} /></span>
        <input type="file" accept="image/*" onChange={e => { if (e.target.files[0]) onAvatar(e.target.files[0]); }} hidden />
      </label>
      <div className="profileHeroText">
        <h2>{profile.display_name || user.name}</h2>
        <p>{profile.bio || 'No bio yet. Add a short field note so other runners know what kind of explorer you are.'}</p>
        <div className="profileChips"><Chip>{profile.runner_type || 'Runner'}</Chip><Chip>Lvl {user.stats.level}</Chip><Chip>{profile.home_area || 'Cape Town'}</Chip></div>
      </div>
      <div className="profileHeroStats"><b>{user.stats.total_km} km</b><span>{user.stats.runs} runs</span></div>
    </div>
  </section>;
}

function ActivityModal({ event, user, onlineIds, commentDraft, setCommentDraft, onReact, onComment, onOpenRun, onClose, formatEvent }) {
  if (!event) return null;
  const isRun = ['run_logged', 'run_imported'].includes(event.type) && event.payload?.run_id;
  const author = event.user || {};
  return <div className="modalBackdrop" onClick={onClose}>
    <div className="modalContent activityModal" onClick={e => e.stopPropagation()}>
      <button className="modalClose" onClick={onClose}><X size={20} /></button>
      <div className="activityPostHead">
        <div className="feedAvatar">{author.avatar_path ? <img src={author.avatar_path} alt="" /> : <UserRound size={18} />}{onlineIds.includes(author.id) && <span className="onlineDot" />}</div>
        <div>
          <span>{event.type.replaceAll('_', ' ')}</span>
          <strong>{author.display_name || author.name || 'Runner'}</strong>
          <time>{new Date(event.created_at).toLocaleString()}</time>
        </div>
      </div>
      <div className="activityPostBody">
        <p>{formatEvent(event)}</p>
        {isRun && <button className="primary" onClick={() => onOpenRun?.(event.payload.run_id)}><BarChart3 size={16} /> Open run dashboard</button>}
      </div>
      <div className="activityPostActions">
        <button className={event.my_reaction === 'open_eye' ? 'active' : ''} onClick={() => onReact(event.id, 'open_eye')}><EyeIcon size={16} /> Eye <b>{event.reactions?.open_eye || 0}</b></button>
        <button className={event.my_reaction === 'closed_eye' ? 'active' : ''} onClick={() => onReact(event.id, 'closed_eye')}><EyeOff size={16} /> Blink <b>{event.reactions?.closed_eye || 0}</b></button>
        <span><MessageCircle size={15} /> {event.comments_count || 0} comments</span>
      </div>
      <div className="activityComments">
        <div className="activityCommentsHead"><strong>Comments</strong><small>{event.comments?.length || 0} visible</small></div>
        {(event.comments || []).length === 0 ? <div className="emptyState compact"><MessageCircle size={22} /><p>No comments yet. Be the first to say something useful.</p></div> : event.comments.map(c => {
          const mine = c.user?.id === user.id;
          return <div key={c.id} className={`activityComment ${mine ? 'mine' : ''}`}>
            <div className="feedAvatar">{c.user?.avatar_path ? <img src={c.user.avatar_path} alt="" /> : <UserRound size={15} />}</div>
            <div><strong>{mine ? 'You' : c.user?.display_name || c.user?.name}</strong><p>{c.body}</p></div>
          </div>;
        })}
        <form className="activityReply" onSubmit={e => { e.preventDefault(); onComment(event.id); }}>
          <input value={commentDraft || ''} onChange={e => setCommentDraft(event.id, e.target.value)} placeholder="Add a comment..." />
          <button className="primary" type="submit" disabled={!commentDraft?.trim()}>Reply</button>
        </form>
      </div>
    </div>
  </div>;
}

function FeedPanel({ user, onlineIds, refreshKey, onOpenRun }) {
  const [events, setEvents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [openComments, setOpenComments] = useState({});
  const [commentDrafts, setCommentDrafts] = useState({});
  const [selectedEventId, setSelectedEventId] = useState(null);
  const loadFeed = useCallback(() => { setLoading(true); api('/api/feed').then(d => setEvents(d.events)).catch(() => setEvents([])).finally(() => setLoading(false)); }, []);
  useEffect(() => { loadFeed(); }, [refreshKey, loadFeed]);
  useEffect(() => {
    const handler = e => { if (e.detail?.type === 'feed.updated') loadFeed(); };
    window.addEventListener('trailbound:realtime', handler);
    return () => window.removeEventListener('trailbound:realtime', handler);
  }, [loadFeed]);
  const react = async (eventId, kind) => { await api(`/api/feed/${eventId}/reaction`, { method: 'POST', body: { kind } }); await loadFeed(); };
  const comment = async (eventId) => { const body = (commentDrafts[eventId] || '').trim(); if (!body) return; await api(`/api/feed/${eventId}/comments`, { method: 'POST', body: { body } }); setCommentDrafts(d => ({ ...d, [eventId]: '' })); setOpenComments(o => ({ ...o, [eventId]: true })); await loadFeed(); };
  const shareEvent = async (ev) => { const text = `Trailbound update: ${(ev.user?.display_name || ev.user?.name || 'A runner')} ${ev.type.replaceAll('_', ' ')} #trailboundapp`; if (navigator.share) await navigator.share({ title: 'Trailbound', text, url: window.location.href }).catch(() => {}); else { await navigator.clipboard?.writeText(text); } };
  const setCommentDraft = (eventId, value) => setCommentDrafts(d => ({ ...d, [eventId]: value }));
  const fmt = (ev) => { const u = ev.user?.display_name || ev.user?.name || 'Someone'; const p = ev.payload || {}; switch (ev.type) { case 'run_logged': case 'run_imported': return <>{u} logged <b>{p.distance_km}km</b> and earned <b>+{p.xp} XP</b></>; case 'status_update': return <>{u} <i>&ldquo;{p.status_text}&rdquo;</i></>; case 'friend_accepted': return <>{u} formed a new trail alliance</>; case 'strava_connected': return <>{u} connected Strava</>; case 'beacon_dropped': return <>{u} dropped a rally beacon</>; default: return <>{u} had activity</>; } };
  const selectedEvent = events.find(ev => ev.id === selectedEventId);
  if (loading) return <Panel eyebrow="Live feed" title="Activity"><div className="skeletonCard"><span className="skeleton skeletonAvatar" /><div className="skeleton skeletonLine med" /><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /></div></Panel>;
  return <>
  <Panel eyebrow="Live feed" title="Activity">{events.length === 0 ? <div className="emptyState"><ActivitySquare size={28} /><p>No activity yet. Log a run or add friends to get started.</p></div> : <div className="feedList richFeed">{events.map(ev => {
    const clickable = ['run_logged', 'run_imported'].includes(ev.type) && ev.payload?.run_id;
    const commentsOpen = !!openComments[ev.id];
    return <article key={ev.id} className="feedItem feedCard clickable">
      <button className="feedMain" onClick={() => setSelectedEventId(ev.id)}>
        <div className="feedAvatar">{ev.user?.avatar_path ? <img src={ev.user.avatar_path} alt="" /> : <UserRound size={18} />}{onlineIds.includes(ev.user?.id) && <span className="onlineDot" />}</div>
        <div className="feedBody"><p>{fmt(ev)}</p><time>{new Date(ev.created_at).toLocaleString()}</time></div>
        <Chip>{ev.type.replaceAll('_', ' ')}</Chip>
      </button>
      <div className="feedActions">
        <button className={ev.my_reaction === 'open_eye' ? 'active' : ''} onClick={() => react(ev.id, 'open_eye')}><span>👁</span>{ev.reactions?.open_eye || 0}</button>
        <button className={ev.my_reaction === 'closed_eye' ? 'active' : ''} onClick={() => react(ev.id, 'closed_eye')}><span>◡</span>{ev.reactions?.closed_eye || 0}</button>
        <button onClick={() => setSelectedEventId(ev.id)}><MessageCircle size={14} />{ev.comments_count || 0}</button>
        <button onClick={() => shareEvent(ev)}><Share2 size={14} />Share</button>
        {clickable && <button onClick={() => onOpenRun?.(ev.payload.run_id)}><BarChart3 size={14} />Run</button>}
      </div>
      {commentsOpen && <div className="feedComments">
        {(ev.comments || []).map(c => <div key={c.id} className="feedComment"><strong>{c.user?.display_name || c.user?.name}</strong><span>{c.body}</span></div>)}
        <form onSubmit={e => { e.preventDefault(); comment(ev.id); }}><input value={commentDrafts[ev.id] || ''} onChange={e => setCommentDraft(ev.id, e.target.value)} placeholder="Add a comment..." /><button className="ghost" type="submit">Reply</button></form>
      </div>}
    </article>;
  })}</div>}</Panel>
  {selectedEvent && <ActivityModal event={selectedEvent} user={user} onlineIds={onlineIds} commentDraft={commentDrafts[selectedEvent.id]} setCommentDraft={setCommentDraft} onReact={react} onComment={comment} onOpenRun={onOpenRun} onClose={() => setSelectedEventId(null)} formatEvent={fmt} />}
  </>;
}

/* ─── Friends ─── */
function FriendsPanel({ user, refreshKey, onNotify }) {
  const [friends, setFriends] = useState(null);
  const [search, setSearch] = useState('');
  const [nickEdit, setNickEdit] = useState(null);
  const [loading, setLoading] = useState(true);
  const load = useCallback(async () => { setLoading(true); try { setFriends(await api('/api/friends')); } catch { setFriends({ friends: [], pending_received: [], pending_sent: [] }); } setLoading(false); }, []);
  useEffect(() => { load(); }, [refreshKey, load]);
  const send = async (identifier) => { try { await api('/api/friends/request', { method: 'POST', body: { identifier } }); setSearch(''); onNotify?.('Friend request sent.'); load(); } catch (err) { onNotify?.(err.message); } };
  const cancel = async (fid) => { await api('/api/friends/cancel', { method: 'POST', body: { friend_id: fid } }); onNotify?.('Friend request cancelled.'); load(); };
  const accept = async (id) => { await api('/api/friends/accept', { method: 'POST', body: { request_id: id } }); onNotify?.('Friend request accepted.'); load(); };
  const reject = async (id) => { await api('/api/friends/reject', { method: 'POST', body: { request_id: id } }); onNotify?.('Friend request rejected.'); load(); };
  const remove = async (fid) => { await api(`/api/friends/${fid}`, { method: 'DELETE' }); onNotify?.('Friend removed.'); load(); };
  const pref = async (fid, body) => { await api(`/api/friends/${fid}/preference`, { method: 'PATCH', body }); onNotify?.('Friend preference saved.'); load(); };
  const nick = async (fid, val) => { await api(`/api/friends/${fid}/nickname`, { method: 'PATCH', body: { friend_id: fid, nickname: val } }); setNickEdit(null); onNotify?.('Nickname saved.'); load(); };
  if (loading) return <Panel eyebrow="Trail allies" title="Friends"><div className="skeletonCard"><span className="skeleton skeletonAvatar" /><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /></div></Panel>;
  if (!friends) return null;
  return <div className="grid">
    <Panel eyebrow="Trail allies" title={`Friends (${friends.friends.length})`}>
      {friends.friends.length === 0 ? <div className="emptyState"><Users size={28} /><p>No allies yet. Search by email, username, or friend code to add one.</p></div> : <div className="friendList">{friends.friends.map(f => <div key={f.id} className="friendItem"><div className="feedAvatar">{f.avatar_path ? <img src={f.avatar_path} alt="" /> : <UserRound size={18} />}</div><div className="friendMeta"><strong>{f.nickname || f.display_name || f.name}</strong>{f.nickname && <small>aka {f.display_name || f.name}</small>}<small>Lvl {f.level} &middot; {f.runner_type} &middot; {f.home_area}</small>{f.muted_at && <small>Muted</small>}</div><div className="friendActions">{nickEdit === f.id ? <form className="inlineForm" onSubmit={e => { e.preventDefault(); nick(f.id, e.target.nickname.value); }}><input name="nickname" defaultValue={f.nickname || ''} placeholder="Nickname" /><button className="primary" type="submit">Save</button><button className="ghost" type="button" onClick={() => setNickEdit(null)}>Cancel</button></form> : <><button className="ghost" onClick={() => pref(f.id, { is_favourite: !f.is_favourite })}>{f.is_favourite ? 'Starred' : 'Star'}</button><button className="ghost" onClick={() => pref(f.id, { muted: !f.muted_at })}>{f.muted_at ? 'Unmute' : 'Mute'}</button><button className="ghost" onClick={() => setNickEdit(f.id)}>Nickname</button><button className="ghost" onClick={() => remove(f.id)}><X size={14} /></button></>}</div></div>)}</div>}
    </Panel>
    <Panel eyebrow="Find allies" title="Add Friend">
      <form onSubmit={e => { e.preventDefault(); if (search.trim()) send(search); }}><label>Email, username, or friend code<input type="text" value={search} onChange={e => setSearch(e.target.value)} placeholder="runner@example.com or TB-1234" required /></label><button className="primary" type="submit"><UserPlus size={16} /> Send Request</button></form>
      {friends.pending_sent.length > 0 && <div className="pendingList">{friends.pending_sent.map(s => <div key={s.id} className="pendingItem"><UserRound size={16} /><span>{s.display_name || s.name}</span><small>Pending</small><button className="ghost mini" onClick={() => cancel(s.id)} type="button">Cancel</button></div>)}</div>}
    </Panel>
    {friends.pending_received.length > 0 && <Panel eyebrow="Incoming" title={`Requests (${friends.pending_received.length})`}><div className="friendList">{friends.pending_received.map(r => <div key={r.id} className="friendItem"><UserRound size={20} /><div className="friendMeta"><strong>{r.display_name || r.name}</strong></div><div className="friendActions"><button className="primary" onClick={() => accept(r.request_id)}><UserCheck size={14} /></button><button className="ghost" onClick={() => reject(r.request_id)}><X size={14} /></button></div></div>)}</div></Panel>}
  </div>;
}

/* ─── Messages ─── */
function MessagesPanel({ friends, selectedFriendId, onHandled, refreshKey, user }) {
  const [conversations, setConversations] = useState([]);
  const [active, setActive] = useState(null);
  const [messages, setMessages] = useState([]);
  const [draft, setDraft] = useState('');
  const [friendId, setFriendId] = useState(selectedFriendId || '');
  const [query, setQuery] = useState('');
  const [chatFilter, setChatFilter] = useState('all');
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await api('/api/messages');
      setConversations(data.conversations || []);
      if (!active && data.conversations?.[0]) setActive(data.conversations[0].id);
    } catch {
      setConversations([]);
    }
    setLoading(false);
  }, [active]);

  useEffect(() => {
    load();
    const id = setInterval(load, 20000);
    return () => clearInterval(id);
  }, [load, refreshKey]);
  useEffect(() => {
    if (!active) { setMessages([]); return; }
    const pull = () => api(`/api/messages/${active}`).then(data => setMessages(data.messages || [])).catch(() => setMessages([]));
    pull();
    const id = setInterval(pull, 15000);
    return () => clearInterval(id);
  }, [active, refreshKey]);

  useEffect(() => {
    const handler = (event) => {
      if (event.detail?.type === 'messages.updated' || event.detail?.type === 'notifications.updated') load();
    };
    window.addEventListener('trailbound:realtime', handler);
    return () => window.removeEventListener('trailbound:realtime', handler);
  }, [load]);

  useEffect(() => {
    if (!selectedFriendId) return;
    setFriendId(selectedFriendId);
    onHandled?.();
  }, [selectedFriendId, onHandled]);

  const start = async (event) => {
    event.preventDefault();
    if (!friendId) return;
    const data = await api('/api/messages/start', { method: 'POST', body: { friend_id: Number(friendId), body: draft || null } });
    setActive(data.conversation.id);
    setDraft('');
    await load();
  };
  const startWithFriend = async (id) => {
    const data = await api('/api/messages/start', { method: 'POST', body: { friend_id: Number(id), body: null } });
    setActive(data.conversation.id);
    setFriendId(String(id));
    setQuery('');
    setDraft('');
    await load();
  };

  const send = async (event) => {
    event.preventDefault();
    if (!active || !draft.trim()) return;
    const data = await api(`/api/messages/${active}`, { method: 'POST', body: { body: draft } });
    setMessages(current => [...current, data.message]);
    setDraft('');
    await load();
  };

  const activeConversation = conversations.find(c => c.id === active);
  const normQuery = query.trim().toLowerCase();
  const filteredConversations = conversations.filter(convo => {
    const other = convo.others?.[0] || {};
    const name = `${other.display_name || ''} ${other.name || ''} ${convo.last_message?.body || ''}`.toLowerCase();
    if (chatFilter === 'unread' && !convo.unread) return false;
    if (chatFilter === 'favourites' && !other.nickname) return false;
    return !normQuery || name.includes(normQuery);
  });
  const suggestedFriends = (friends || []).filter(friend => {
    const name = `${friend.nickname || ''} ${friend.display_name || ''} ${friend.name || ''}`.toLowerCase();
    const hasConversation = conversations.some(convo => String(convo.others?.[0]?.id) === String(friend.id));
    return normQuery && name.includes(normQuery) && !hasConversation;
  }).slice(0, 5);
  const unreadCount = conversations.filter(convo => convo.unread).length;

  return <div className="messagesLayout premiumMessages">
    <aside className="chatListPanel">
      <div className="chatListHeader">
        <h2>Chats</h2>
        <div>
          <button className="iconBtn" title="New chat"><SquarePlus size={18} /></button>
          <button className="iconBtn" title="More"><MoreVertical size={18} /></button>
        </div>
      </div>
      <label className="chatSearch"><Search size={17} /><input value={query} onChange={e => setQuery(e.target.value)} placeholder="Search or start a new chat" /></label>
      <div className="chatFilterPills">
        <button className={chatFilter === 'all' ? 'active' : ''} onClick={() => setChatFilter('all')}>All</button>
        <button className={chatFilter === 'unread' ? 'active' : ''} onClick={() => setChatFilter('unread')}>Unread {unreadCount || ''}</button>
        <button className={chatFilter === 'favourites' ? 'active' : ''} onClick={() => setChatFilter('favourites')}>Favourites</button>
      </div>
      {suggestedFriends.length > 0 && <div className="chatSuggestions">
        <span>Start new chat</span>
        {suggestedFriends.map(friend => <button key={friend.id} onClick={() => startWithFriend(friend.id)}>
          <span className="feedAvatar">{friend.avatar_path ? <img src={friend.avatar_path} alt="" /> : <UserRound size={16} />}</span>
          <strong>{friend.nickname || friend.display_name || friend.name}</strong>
          <small>Friend</small>
        </button>)}
      </div>}
      {loading ? <div className="chatListLoading"><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /></div> : <div className="conversationList conversationRail whatsappRail">{filteredConversations.map(convo => {
        const other = convo.others?.[0] || {};
        return <button key={convo.id} className={active === convo.id ? 'active' : ''} onClick={() => setActive(convo.id)}>
          <span className="feedAvatar">{other.avatar_path ? <img src={other.avatar_path} alt="" /> : <UserRound size={18} />}</span>
          <span><strong>{other.display_name || other.name || 'Runner'}</strong><small>{convo.last_message?.body || 'No messages yet'}</small></span>
          <time>{convo.last_message_at ? new Date(convo.last_message_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'New'}</time>
          {convo.unread && <i />}
        </button>;
      })}{filteredConversations.length === 0 && <div className="emptyState compact"><MessageCircle size={22} /><p>{normQuery ? 'No matching chats. Search a friend to start one.' : 'No chats yet. Search a friend above and start one.'}</p></div>}</div>}
    </aside>
    <section className="chatPanel">
      <div className="chatHeader">
        <div className="feedAvatar">{activeConversation?.others?.[0]?.avatar_path ? <img src={activeConversation.others[0].avatar_path} alt="" /> : <UserRound size={18} />}</div>
        <div><span>{activeConversation?.others?.[0]?.runner_type || 'Direct'}</span><strong>{activeConversation?.others?.[0]?.display_name || activeConversation?.others?.[0]?.name || 'Select a conversation'}</strong></div>
        <Chip>{active ? 'Live thread' : 'Choose a friend'}</Chip>
      </div>
      <div className="chatPane">
        <div className="chatMessages">
          {messages.length === 0 ? <div className="emptyState"><MessageCircle size={28} /><p>No messages yet. Drop the first shard whisper.</p></div> : messages.map(message => {
            const mine = message.user.id === user.id;
            return <div key={message.id} className={`chatBubble ${mine ? 'mine' : 'theirs'}`}><strong>{mine ? 'You' : message.user.display_name || message.user.name}</strong><p>{message.body}</p><time>{new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</time></div>;
          })}
        </div>
        <form className="chatComposer" onSubmit={send}>
          <input value={draft} onChange={e => setDraft(e.target.value)} placeholder="Message your friend..." />
          <button className="primary" type="submit" disabled={!draft.trim() || !active}>Send</button>
        </form>
      </div>
    </section>
  </div>;
}

function TearsIcon({ size = 16 }) {
  return <Droplet size={size} className="tearsIcon" />;
}

function WalletDisplay({ balance, compact = false }) {
  if (compact) return <span className="walletCompact"><Droplet size={14} />{balance ?? 0}</span>;
  return <div className="walletBadge"><Droplet size={15} /><span>{balance ?? 0} Tears</span></div>;
}

function BadgeCard({ badge, current = false, next = false }) {
  const locked = !badge.unlocked && !badge.earned;
  return (
    <div className={`badgeCard ${badge.unlocked ? 'unlocked' : locked ? 'locked' : 'earned'} ${current ? 'current' : ''} ${next ? 'next' : ''}`}>
      <span className="badgeEmblem">{badge.icon || <Star size={28} />}</span>
      <div className="badgeInfo">
        <strong>{badge.name}</strong>
        <small>{badge.description || `Reach level ${badge.level_required}`}</small>
        <span className="badgeLevel">Lvl {badge.level_required}{badge.unlocked ? ' · Unlocked' : locked ? ' · Locked' : ' · Earned'}</span>
      </div>
    </div>
  );
}

function ItemCard({ item, ownedQuantity, compact = false, onClick }) {
  const rarityColors = { common: 'var(--muted)', magic: '#60a5fa', rare: '#a78bfa', epic: '#f59e0b', legendary: '#fb7185' };
  const color = rarityColors[item.rarity] || rarityColors.common;
  return (
    <button className={`itemCard ${item.rarity} ${compact ? 'compact' : ''}`} onClick={onClick} style={{ '--rarity-color': color }}>
      <span className="itemRarityLine" style={{ background: color }} />
      <span className="itemIcon">{item.icon || <Gem size={compact ? 20 : 28} />}</span>
      <div className="itemBody">
        <strong>{item.name}</strong>
        {!compact && <small>{item.description || 'A Trailbound item.'}</small>}
        <div className="itemTags">
          <span className="rarityChip" style={{ color, borderColor: color }}>{item.rarity}</span>
          {item.type && <span className="typeChip">{item.type}</span>}
          {ownedQuantity > 1 && <span className="qtyChip">x{ownedQuantity}</span>}
        </div>
      </div>
    </button>
  );
}

function InventoryPanel({ refreshKey }) {
  const [items, setItems] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  const load = useCallback(() => { setLoading(true); api('/api/inventory').then(d => setItems(d.items)).catch(() => setItems([])).finally(() => setLoading(false)); }, []);
  useEffect(() => { load(); }, [refreshKey, load]);
  if (loading) return <Panel eyebrow="Carried gear" title="Inventory"><div className="skeletonCard"><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /></div></Panel>;
  const filtered = (items || []).filter(i => filter === 'all' || i.rarity === filter || i.type === filter);
  const rarities = ['common', 'magic', 'rare', 'epic', 'legendary'];
  return <div className="grid">
    <Panel eyebrow={`${items?.length || 0} items`} title="Your Inventory">
      <div className="inventoryFilters">
        <button className={filter === 'all' ? 'primary' : 'ghost'} onClick={() => setFilter('all')}>All</button>
        {rarities.map(r => <button key={r} className={`${filter === r ? 'primary' : 'ghost'} rarityBtn`} style={{ '--rarity-color': r === 'common' ? 'var(--muted)' : r === 'magic' ? '#60a5fa' : r === 'rare' ? '#a78bfa' : r === 'epic' ? '#f59e0b' : '#fb7185' }} onClick={() => setFilter(r)}>{r}</button>)}
      </div>
      {(filtered || []).length === 0 ? <div className="emptyState"><Gem size={28} /><p>{items?.length ? 'No items match this filter.' : 'Your inventory is empty. Earn items from quests, challenges, or the shop.'}</p></div> : <div className="inventoryGrid">{filtered.map(item => <ItemCard key={item.id} item={item} ownedQuantity={item.quantity} />)}</div>}
    </Panel>
  </div>;
}

function ShopPanel({ refreshKey, onBalanceUpdate, user }) {
  const [shop, setShop] = useState(null);
  const [loading, setLoading] = useState(true);
  const [message, setMessage] = useState('');
  const load = useCallback(() => { setLoading(true); api('/api/shop').then(d => { setShop(d); if (onBalanceUpdate) onBalanceUpdate(d.balance); }).catch(() => setShop(null)).finally(() => setLoading(false)); }, [onBalanceUpdate]);
  useEffect(() => { load(); }, [refreshKey, load]);
  const buy = async (shopItemId) => { try { const d = await api(`/api/shop/${shopItemId}/buy`, { method: 'POST' }); setMessage(d.message); if (onBalanceUpdate) onBalanceUpdate(d.balance); load(); } catch (err) { setMessage(err.message); } };
  if (loading) return <Panel eyebrow="Shard market" title="Shop"><div className="skeletonCard"><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /></div></Panel>;
  return <div className="grid">
    {message && <p className="notice">{message}</p>}
    <Panel eyebrow={`${shop?.balance || 0} Tears`} title="Shard Shop">
      {!shop?.shop_items?.length ? <div className="emptyState"><ShoppingBag size={28} /><p>The shop is empty. Check back soon for new items.</p></div> : <div className="shopGrid">{(shop.shop_items || []).map(si => (
        <div key={si.id} className={`shopCard ${si.owned ? 'owned' : ''} ${!si.unlocked ? 'locked' : ''}`}>
          <span className="shopRarity" style={{ background: si.item.rarity === 'common' ? 'var(--muted)' : si.item.rarity === 'magic' ? '#60a5fa' : si.item.rarity === 'rare' ? '#a78bfa' : si.item.rarity === 'epic' ? '#f59e0b' : '#fb7185' }} />
          <div className="shopItemIcon">{si.item.icon || <Gem size={32} />}</div>
          <div className="shopItemInfo">
            <strong>{si.item.name}</strong>
            <span className="rarityChip" style={{ color: si.item.rarity === 'common' ? 'var(--muted)' : si.item.rarity === 'magic' ? '#60a5fa' : si.item.rarity === 'rare' ? '#a78bfa' : si.item.rarity === 'epic' ? '#f59e0b' : '#fb7185', borderColor: si.item.rarity === 'common' ? 'var(--muted)' : si.item.rarity === 'magic' ? '#60a5fa' : si.item.rarity === 'rare' ? '#a78bfa' : si.item.rarity === 'epic' ? '#f59e0b' : '#fb7185' }}>{si.item.rarity}</span>
            <small>{si.item.description}</small>
          </div>
          <div className="shopItemPrice">
            <span><Droplet size={15} />{si.price_tears}</span>
            {si.owned ? <span className="ownedBadge">Owned</span> : !si.unlocked ? <span className="lockedBadge"><Lock size={12} /> Lvl {si.level_required}</span> : <button className="primary" onClick={() => buy(si.id)} disabled={(shop?.balance || 0) < si.price_tears}>Buy</button>}
          </div>
        </div>
      ))}</div>}
    </Panel>
  </div>;
}

function SkillTreePanel({ refreshKey }) {
  const [tree, setTree] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(null);
  const load = useCallback(() => { setLoading(true); api('/api/skills/tree').then(setTree).catch(() => setTree(null)).finally(() => setLoading(false)); }, []);
  useEffect(() => { load(); }, [refreshKey, load]);
  const unlock = async (nodeId) => { try { await api(`/api/skills/${nodeId}/unlock`, { method: 'POST' }); load(); setSelected(null); } catch (err) { alert(err.message); } };
  const respec = async () => {
    const cost = tree?.respec_cost_tears || 0;
    if (!confirm(cost ? `Respec costs ${cost} Tears. Reset your skill tree?` : 'Use your weekly free respec and reset your skill tree?')) return;
    try { const d = await api('/api/skills/respec', { method: 'POST' }); alert(d.message); load(); } catch (err) { alert(err.message); }
  };
  if (loading) return <Panel eyebrow="Progression" title="Skill Tree"><div className="skeletonCard"><div className="skeleton skeletonBlock" /></div></Panel>;
  const branches = tree?.branches || {};
  const branchNames = { endurance: 'Endurance', explorer: 'Explorer', tempo: 'Tempo', social: 'Social' };
  const branchColors = { endurance: '#fb7185', explorer: '#60a5fa', tempo: '#7dd3a8', social: '#facc15' };
  const branchIcons = { endurance: Timer, explorer: Compass, tempo: Gauge, social: Users };

  return <div className="skillTreeLayout">
    <div className="skillTreeBar">
      <span className="kicker">Skill tree</span>
      <h2>Runner progression</h2>
      <div className="skillPointPills">
        <Chip tone="good">{tree?.skill_points || 0} points</Chip>
        <Chip>{tree?.spent_points || 0} spent</Chip>
        <WalletDisplay balance={tree?.tears || 0} compact />
        <button className="ghost" onClick={respec} disabled={!tree?.spent_points}><Sparkles size={14} /> Respec {tree?.respec_cost_tears ? `${tree.respec_cost_tears} Tears` : 'free'}</button>
      </div>
    </div>
    <div className="skillTreeExplainer">
      <strong>Earn 1 skill point every time you level up.</strong>
      <span>{tree?.free_respec_available ? 'Your weekly free respec is ready.' : `Next free respec: ${tree?.next_free_respec_at ? new Date(tree.next_free_respec_at).toLocaleString() : 'soon'}. Until then it costs 10 Tears.`}</span>
    </div>
    <div className="skillBranches">
      {Object.entries(branches).map(([branchName, nodes]) => {
        const BranchIcon = branchIcons[branchName] || Sword;
        const maxTier = Math.max(...(nodes || []).map(n => n.tier), 1);
        const tiers = Array.from({ length: maxTier }, (_, i) => i + 1);
        return (
          <div key={branchName} className="skillBranch">
            <div className="branchHeader" style={{ borderColor: branchColors[branchName] }}>
              <BranchIcon size={18} style={{ color: branchColors[branchName] }} />
              <strong style={{ color: branchColors[branchName] }}>{branchNames[branchName] || branchName}</strong>
            </div>
            <div className="branchTiers">
              {tiers.map(tier => {
                const tierNodes = (nodes || []).filter(n => n.tier === tier).sort((a, b) => a.position - b.position);
                return (
                  <div key={tier} className="skillTier">
                    <span className="tierLabel">Tier {tier}</span>
                    <div className="tierNodes">
                      {tierNodes.map((node, idx) => (
                        <div key={node.id} className="skillNodeWrap">
                          {tier > 1 && <div className={`nodePath ${node.unlocked ? 'active' : ''}`} style={{ borderColor: node.unlocked ? branchColors[branchName] : 'var(--border)' }} />}
                          <button
                            className={`skillNode ${node.unlocked ? 'unlocked' : node.available ? 'available' : 'locked'}`}
                            onClick={() => setSelected(node)}
                            style={{ '--branch-color': branchColors[branchName] }}
                          >
                            <span className="nodeIcon">{React.createElement(iconMap[node.icon] || Star, { size: 17 })}</span>
                            <span className="nodeName">{node.name}</span>
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        );
      })}
    </div>
    {selected && (
      <div className="modalBackdrop" onClick={() => setSelected(null)}>
        <div className="modalContent skillDetailModal" onClick={e => e.stopPropagation()}>
          <button className="modalClose" onClick={() => setSelected(null)}><X size={20} /></button>
          <span className="kicker">Skill node</span>
          <h2>{selected.name}</h2>
          <p className="muted">{selected.description || 'A progression node on the skill tree.'}</p>
          {selected.effect && <div className="skillEffect"><Sparkles size={16} /><span>{selected.effect}</span></div>}
          <div className="questDetailGrid">
            <div><b>{selected.branch}</b><span>branch</span></div>
            <div><b>Tier {selected.tier}</b><span>tier</span></div>
            <div><b>{selected.requirement_value}</b><span>{selected.requirement_type}</span></div>
            <div><b>{selected.cost_tears > 0 ? `${selected.cost_tears} Tears` : 'Free'}</b><span>cost</span></div>
          </div>
          <div className="skillActions">
            {selected.unlocked ? <span className="chip good">Unlocked</span> : selected.available ? <button className="primary" onClick={() => unlock(selected.id)} disabled={(tree?.skill_points || 0) < 1}>Unlock for 1 point</button> : <span className="chip">Requirements not met</span>}
          </div>
        </div>
      </div>
    )}
  </div>;
}

function ChallengesPanel({ refreshKey, friends, user }) {
  const [official, setOfficial] = useState(null);
  const [friendChallenges, setFriendChallenges] = useState(null);
  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState('official');
  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState({ friend_id: '', title: '', goal_type: 'distance_km', goal_value: 5, reward_tears: 10 });
  const load = useCallback(() => {
    setLoading(true);
    Promise.all([api('/api/challenges/official'), api('/api/challenges/friends')])
      .then(([o, f]) => { setOfficial(o.challenges); setFriendChallenges(f.challenges); })
      .catch(() => { setOfficial([]); setFriendChallenges([]); })
      .finally(() => setLoading(false));
  }, []);
  useEffect(() => { load(); }, [refreshKey, load]);
  const createChallenge = async (e) => { e.preventDefault(); try { await api('/api/challenges', { method: 'POST', body: createForm }); setShowCreate(false); setCreateForm({ friend_id: '', title: '', goal_type: 'distance_km', goal_value: 5, reward_tears: 10 }); load(); } catch (err) { alert(err.message); } };
  const acceptChallenge = async (id) => { await api(`/api/challenges/${id}/accept`, { method: 'POST' }); load(); };
  const declineChallenge = async (id) => { await api(`/api/challenges/${id}/decline`, { method: 'POST' }); load(); };
  const claimReward = async (id) => { try { const d = await api(`/api/challenges/${id}/claim`, { method: 'POST' }); alert(d.message); load(); } catch (err) { alert(err.message); } };
  if (loading) return <Panel eyebrow="Events" title="Challenges"><div className="skeletonCard"><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /></div></Panel>;

  const formatGoal = (c) => c.goal_label || `${c.goal_type.replaceAll('_', ' ')}: ${c.goal_value}`;
  const timeRemaining = (c) => {
    if (!c.time_remaining) return null;
    const hours = Math.floor(c.time_remaining / 3600);
    const days = Math.floor(hours / 24);
    if (days > 0) return `${days}d ${hours % 24}h`;
    if (hours > 0) return `${hours}h`;
    return 'Ending soon';
  };

  return <div className="grid">
    <div className="challengeTabs">
      <button className={tab === 'official' ? 'primary' : 'ghost'} onClick={() => setTab('official')}><Trophy size={16} /> Trailbound</button>
      <button className={tab === 'friends' ? 'primary' : 'ghost'} onClick={() => setTab('friends')}><Users size={16} /> Friends</button>
    </div>
    {tab === 'official' && <Panel eyebrow="Trailbound events" title="Daily · Weekly · Monthly">
      {(official || []).length === 0 ? <div className="emptyState"><Trophy size={28} /><p>No active official challenges. Check back soon for new events.</p></div> : <div className="challengeList">{official.map(c => (
        <div key={c.id} className={`challengeCard ${c.my_status === 'completed' ? 'done' : ''}`}>
          <div className="challengeInfo">
            <strong>{c.title}</strong>
            <span className="challengeGoal">{formatGoal(c)}</span>
            <div className="challengeRewards">{c.reward_xp > 0 && <span className="chip">+{c.reward_xp} XP</span>}{c.reward_tears > 0 && <span className="chip"><Droplet size={12} />+{c.reward_tears}</span>}</div>
          </div>
          <div className="challengeRight">
            {c.time_remaining ? <span className="challengeTimer"><Timer size={14} />{timeRemaining(c)}</span> : null}
            <div className="challengeProgress"><div className="challengeBar"><i style={{ width: `${c.my_progress || 0}%` }} /></div><span>{c.my_progress || 0}%</span></div>
          </div>
        </div>
      ))}</div>}
    </Panel>}
    {tab === 'friends' && <>
      <Panel eyebrow="Friend duels" title="Active Challenges">
        <div className="panelActions"><button className="primary" onClick={() => setShowCreate(true)}><Sword size={15} /> Challenge a friend</button></div>
        {(friendChallenges || []).length === 0 ? <div className="emptyState"><Sword size={28} /><p>No friend challenges yet. Challenge a friend to a running goal.</p></div> : <div className="challengeList">{friendChallenges.map(c => (
          <div key={c.id} className={`challengeCard friend ${c.my_status}`}>
            <div className="challengeInfo">
              <strong>{c.title}</strong>
              <span className="challengeGoal">{formatGoal(c)}</span>
              <div className="challengeParticipants">
                {(c.participants || []).map(p => (
                  <span key={p.id} className="participantBadge" style={{ opacity: p.status === 'declined' ? 0.4 : 1 }}>
                    <span className="feedAvatar" style={{ width: 22, height: 22 }}>{p.avatar_path ? <img src={p.avatar_path} alt="" /> : <UserRound size={12} />}</span>
                    <span>{p.display_name || p.name}</span>
                    <small>{p.progress}%</small>
                  </span>
                ))}
              </div>
            </div>
            <div className="challengeRight">
              {c.my_status === 'active' && <span className="challengeTimer"><Timer size={14} />{timeRemaining(c)}</span>}
              {c.my_status === 'completed' && !c.reward_claimed_at && <button className="primary" onClick={() => claimReward(c.id)}>Claim reward</button>}
              {c.my_status === 'declined' && <span className="chip quiet">Declined</span>}
              {!c.my_status && c.status === 'active' && <div className="friendActions"><button className="primary" onClick={() => acceptChallenge(c.id)}>Accept</button><button className="ghost" onClick={() => declineChallenge(c.id)}>Decline</button></div>}
            </div>
          </div>
        ))}</div>}
      </Panel>
      {showCreate && <div className="modalBackdrop" onClick={() => setShowCreate(false)}><div className="modalContent" onClick={e => e.stopPropagation()}>
        <button className="modalClose" onClick={() => setShowCreate(false)}><X size={20} /></button>
        <span className="kicker">New challenge</span><h2>Challenge a friend</h2>
        <form className="form" onSubmit={createChallenge}>
          <label>Friend<select value={createForm.friend_id} onChange={e => setCreateForm({ ...createForm, friend_id: e.target.value })} required><option value="">Select friend...</option>{(friends || []).map(f => <option key={f.id} value={f.id}>{f.nickname || f.display_name || f.name}</option>)}</select></label>
          <label>Title<input value={createForm.title} onChange={e => setCreateForm({ ...createForm, title: e.target.value })} placeholder="First to 5km this week" required /></label>
          <div className="split">
            <label>Goal type<select value={createForm.goal_type} onChange={e => setCreateForm({ ...createForm, goal_type: e.target.value })}><option value="distance_km">Distance (km)</option><option value="runs_count">Run count</option><option value="streak_days">Streak days</option></select></label>
            <label>Target<input type="number" value={createForm.goal_value} onChange={e => setCreateForm({ ...createForm, goal_value: Number(e.target.value) })} min={1} /></label>
          </div>
          <label>Reward Tears (optional)<input type="number" value={createForm.reward_tears} onChange={e => setCreateForm({ ...createForm, reward_tears: Number(e.target.value) })} min={0} max={1000} /></label>
          <button className="primary" type="submit">Send challenge</button>
        </form>
      </div></div>}
    </>}
  </div>;
}

function PackageCard({ pkg, selected, onSelect, showSelect = true }) {
  const features = Array.isArray(pkg.features) ? pkg.features : (pkg.features ? JSON.parse(pkg.features) : []);
  return (
    <div className={`packageCard ${selected ? 'selected' : ''}`}>
      <div className="packageHead">
        <h3>{pkg.name}</h3>
        <div className="packagePrice">
          {pkg.price_cents > 0 ? <><b>${(pkg.price_cents / 100).toFixed(2)}</b><small>/{pkg.billing_interval || 'mo'}</small></> : <b>Free</b>}
        </div>
      </div>
      <p className="packageDesc">{pkg.description || 'A Trailbound adventure package.'}</p>
      <ul className="packageFeatures">
        {features.map((f, i) => <li key={i}><Sparkles size={14} />{f}</li>)}
      </ul>
      {showSelect && pkg.price_cents === 0 && <button className={selected ? 'primary' : 'ghost'} onClick={() => onSelect?.(pkg.id)}>{selected ? 'Selected' : 'Choose Free'}</button>}
      {showSelect && pkg.price_cents > 0 && <button className="ghost" disabled>Coming soon</button>}
    </div>
  );
}

/* ─── Combined Social Panel ─── */
function SocialPanel({ user, onlineIds, refreshKey, onOpenRun, setMessage, friends }) {
  const [tab, setTab] = useState('feed');
  return (
    <div className="socialLayout">
      <div className="socialTabs">
        <button className={tab === 'feed' ? 'primary' : 'ghost'} onClick={() => setTab('feed')}><ActivitySquare size={16} />Feed</button>
        <button className={tab === 'friends' ? 'primary' : 'ghost'} onClick={() => setTab('friends')}><Users size={16} />Friends</button>
        <button className={tab === 'challenges' ? 'primary' : 'ghost'} onClick={() => setTab('challenges')}><Sword size={16} />Challenges</button>
      </div>
      {tab === 'feed' && <FeedPanel user={user} onlineIds={onlineIds} refreshKey={refreshKey} onOpenRun={onOpenRun} />}
      {tab === 'friends' && <FriendsPanel user={user} refreshKey={refreshKey} onNotify={setMessage} />}
      {tab === 'challenges' && <ChallengeMiniCard friends={friends} user={user} refreshKey={refreshKey} />}
    </div>
  );
}

function ChallengeMiniCard({ friends, user, refreshKey }) {
  const [challenges, setChallenges] = useState(null);
  useEffect(() => { api('/api/challenges/friends').then(d => setChallenges(d.challenges || [])).catch(() => setChallenges([])); }, [refreshKey]);
  if (!challenges) return <div className="skeletonCard"><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /></div>;
  return <Panel eyebrow="Friend duels" title={`${challenges.length} active`}>
    {challenges.length === 0 ? <div className="emptyState"><Sword size={28} /><p>No friend challenges yet. Challenge a friend from the Challenges tab.</p></div> : <div className="challengeList">{challenges.slice(0, 5).map(c => (
      <div key={c.id} className={`challengeCard mini ${c.my_status}`}>
        <strong>{c.title}</strong>
        <div className="challengeMiniBar"><i style={{ width: `${c.my_progress || 0}%` }} /></div>
        <small>{c.goal_label}</small>
      </div>
    ))}</div>}
  </Panel>;
}

function PackageInfoPanel({ user }) {
  const pkg = user.package;
  if (!pkg) return null;
  const features = Array.isArray(pkg.features) ? pkg.features : (pkg.features ? JSON.parse(pkg.features) : []);
  return (
    <Panel eyebrow="Subscription" title={pkg.name}>
      <div className="packageInfoPanel">
        <div className="packageInfoHeader"><PackageIcon size={20} /><span>{pkg.name} plan</span></div>
        {features.length > 0 && <ul className="packageFeatures compact">{features.map((f, i) => <li key={i}><Sparkles size={12} />{f}</li>)}</ul>}
        <p className="muted">Payment processing is not yet live. Paid packages will be available soon.</p>
      </div>
    </Panel>
  );
}

function FriendCodeCard({ user, onMessage }) {
  const code = user.profile?.friend_code || 'Generating...';
  const shareText = `Add me on Trailbound with friend code ${code} #trailboundapp`;
  const copy = async () => {
    await navigator.clipboard?.writeText(code);
    onMessage?.('Friend code copied.');
  };
  const share = async () => {
    if (navigator.share) {
      await navigator.share({ title: 'Trailbound friend code', text: shareText });
    } else {
      await navigator.clipboard?.writeText(shareText);
      onMessage?.('Share text copied.');
    }
  };
  return <Panel eyebrow="Friend code" title={code}>
    <p className="muted">Use this for friend requests and signup referrals. It is unique to you.</p>
    <div className="actions"><button className="primary" onClick={copy}><Copy size={15} />Copy code</button><button className="ghost" onClick={share}><Share2 size={15} />Share</button></div>
  </Panel>;
}

function ProgressPanel({ user, world, tasks, onNavigate }) {
  const unlocked = world.regions.filter(r => r.status !== 'locked');
  const completeTasks = tasks.filter(t => t.status === 'complete').length;
  const weeklyGoal = Number(user.profile?.weekly_goal_km || 15);
  const weeklyProgress = Math.min(100, weeklyGoal ? ((Number(user.stats?.total_km || 0) % weeklyGoal) / weeklyGoal) * 100 : 0);
  const nextTask = tasks.find(t => t.status === 'available' || t.status === 'in_progress') || tasks.find(t => t.status !== 'locked');
  const xpProgress = Math.min(100, ((Number(user.stats?.xp || 0) % 500) / 500) * 100);
  return <div className="progressPage">
    <Panel eyebrow="Next objective" title={nextTask ? nextTask.title : 'Log your first run'}>
      <div className="nextObjective">
        <Compass size={28} />
        <div><p>{nextTask ? `${nextTask.region} · ${nextTask.unlock_rule}` : 'Use your location, log a run, and Trailbound will start revealing your shard.'}</p><button className="primary" onClick={() => onNavigate(nextTask ? 'Tasks' : 'Runs')}>{nextTask ? 'Open quest board' : 'Log a run'}</button></div>
      </div>
    </Panel>
    <div className="runsSummary">
      <PremiumStatCard icon={Star} value={`Lvl ${user.stats?.level || 1}`} label="Level progress" context={`${Number(user.stats?.xp || 0) % 500} / 500 XP`} progress={xpProgress} />
      <PremiumStatCard icon={Compass} value={`${completeTasks}/${tasks.length}`} label="Quest progress" context={nextTask ? `Next: ${nextTask.title}` : 'Quest chain clear'} progress={tasks.length ? (completeTasks / tasks.length) * 100 : 0} />
      <PremiumStatCard icon={Map} value={`${unlocked.length}/${world.regions.length}`} label="Map unlocks" context="Cape Town shard revealed" progress={world.regions.length ? (unlocked.length / world.regions.length) * 100 : 0} />
      <PremiumStatCard icon={Droplet} value={user.stats?.tears || 0} label="Tears balance" context="Spend in shop or skill tree" progress={Math.min(100, Number(user.stats?.tears || 0))} />
    </div>
    <Panel eyebrow="Weekly momentum" title={`${weeklyGoal} km goal`}>
      <div className="questProgress large"><i style={{ width: `${weeklyProgress}%` }} /></div>
      <p className="muted">{weeklyProgress.toFixed(0)}% of your rolling weekly goal signal. Connect Strava or log runs to keep this moving.</p>
    </Panel>
    <Panel eyebrow="Build path" title={user.profile?.runner_type || 'Pathfinder'}>
      <div className="progressChecklist">
        {['Detect your current shard', 'Log or sync a run', 'Complete a quest', 'Earn Tears', 'Share an achievement', 'Challenge a friend'].map((item, i) => <div key={item} className={i < Math.min(2, user.stats?.runs || 0) + 1 ? 'done' : ''}><CheckCircle2 size={16} /><span>{item}</span></div>)}
      </div>
    </Panel>
  </div>;
}

function HelpPanel({ onReplayTutorial }) {
  const topics = [
    ['Runs', 'Runs generate XP, Tears, quest progress, social posts, and map unlocks.'],
    ['Shards', 'Cape Town is divided into real-world regions with game names, biomes, quests, and fog-of-war.'],
    ['Tears', 'Tears are the Trailbound reward currency used for items, shop purchases, and future skill unlocks.'],
    ['Quests', 'Quests are region objectives unlocked by location, distance, consistency, and task chains.'],
    ['Classes', 'Runner classes set your identity and future skill-tree direction.'],
    ['Social', 'The Social tab combines your feed, friends, reactions, comments, challenges, and bragging moments.'],
    ['Friend codes', 'Friend codes let people add you and can credit referrals during signup.'],
    ['Packages', 'Free is active now. Paid packages are admin-managed and future-ready for payment integration.'],
  ];
  return <div className="helpPage">
    <Panel eyebrow="Trailbound Bible" title="How the world opens">
      <p className="muted">Trailbound turns real movement into a living progression game. Run outside, reveal territory, complete quests, earn Tears, and bring friends into the same map.</p>
      <button className="primary" onClick={onReplayTutorial}><Sparkles size={15} />Replay welcome tour</button>
    </Panel>
    <div className="helpGrid">{topics.map(([title, body]) => <article key={title} className="helpTopic"><strong>{title}</strong><p>{body}</p></article>)}</div>
  </div>;
}

function TutorialModal({ onClose }) {
  const steps = [
    ['Welcome to Trailbound', 'Your real-world runs become quests, XP, Tears, social moments, and exploration.'],
    ['Start where you are', 'Your first shard is based on your physical location. Cape Town opens as you move through it.'],
    ['Choose your class', 'Your runner type shapes your identity and future skill-tree path.'],
    ['Know what to do next', 'Progress shows level, quests, weekly goals, map unlocks, Tears, and your next objective.'],
  ];
  const [step, setStep] = useState(0);
  const done = step >= steps.length - 1;
  return <div className="modalBackdrop" onClick={onClose}>
    <div className="modalContent tutorialModal" onClick={e => e.stopPropagation()}>
      <button className="modalClose" onClick={onClose}><X size={20} /></button>
      <span className="kicker">Orrin guide</span>
      <Eye phrase="Let's open the shard properly." />
      <h2>{steps[step][0]}</h2>
      <p className="muted">{steps[step][1]}</p>
      <div className="tutorialDots">{steps.map((_, i) => <i key={i} className={i === step ? 'active' : ''} />)}</div>
      <div className="actions"><button className="ghost" onClick={onClose}>Skip</button><button className="primary" onClick={() => done ? onClose() : setStep(s => s + 1)}>{done ? 'Enter Trailbound' : 'Next'}</button></div>
    </div>
  </div>;
}

function BadgeGallery({ refreshKey }) {
  const [badges, setBadges] = useState(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => { setLoading(true); api('/api/badges').then(d => setBadges(d)).catch(() => setBadges(null)).finally(() => setLoading(false)); }, [refreshKey]);
  if (loading) return <Panel eyebrow="Achievements" title="Badges"><div className="skeletonCard"><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /></div></Panel>;
  const allBadges = badges?.badges || [];
  const current = badges?.current_badge;
  const next = badges?.next_badge;
  return (
    <Panel eyebrow={`Level ${badges?.level || 1}`} title="Runner Badges">
      {allBadges.length === 0 ? <div className="emptyState"><Star size={28} /><p>No badges available yet. Level up by logging runs.</p></div> : (
        <div className="badgeGallery">
          {current && <div className="badgeHighlight"><span className="kicker">Current badge</span><BadgeCard badge={current} current /></div>}
          {next && <div className="badgeHighlight"><span className="kicker">Next badge</span><BadgeCard badge={next} next /></div>}
          <div className="badgeGrid">
            {allBadges.filter(b => b.id !== current?.id && b.id !== next?.id).slice(0, 8).map(b => <BadgeCard key={b.id} badge={b} />)}
          </div>
        </div>
      )}
    </Panel>
  );
}
function NotificationCenter({ data, open, onToggle, onNavigate, onEnableBrowser, onRefresh }) {
  const unread = data?.unread_count || 0;
  const items = data?.items || [];
  const openItem = async (item) => {
    if (item.notification_id) {
      await api(`/api/notifications/${item.notification_id}/read`, { method: 'POST' }).catch(() => {});
      onRefresh?.();
    }
    onNavigate(item.action);
  };
  const readAll = async () => {
    await api('/api/notifications/read-all', { method: 'POST' }).catch(() => {});
    onRefresh?.();
  };
  return <div className="notifyWrap">
    <button className={`iconBtn notifyBtn${open ? ' active' : ''}`} onClick={onToggle} title="Notifications">
      <Bell size={17} />
      {unread > 0 && <span className="notifyBadge">{unread}</span>}
    </button>
    {open && <div className="notifyPanel">
      <div className="notifyHead">
        <div><strong>Notifications</strong><small>{unread > 0 ? `${unread} needs attention` : 'All caught up'}</small></div>
        <div className="notifyHeadActions"><button className="ghost mini" onClick={readAll}>Mark read</button><button className="ghost mini" onClick={onEnableBrowser}>Browser alerts</button></div>
      </div>
      {items.length === 0 ? <div className="emptyState" style={{ padding: '18px 8px' }}><Bell size={22} /><p style={{ fontSize: '.78rem' }}>No updates yet. The shard is quiet.</p></div> : <div className="notifyList">
        {items.map(item => <button key={item.id} onClick={() => openItem(item)} className={`${item.kind === 'message' || item.kind === 'friend_request' ? 'important' : ''}${item.read_at ? ' read' : ' unread'}`}>
          <span className="notifyKind">{item.kind.replaceAll('_', ' ')}</span>
          <strong>{item.title}</strong>
          <small>{item.body}</small>
        </button>)}
      </div>}
    </div>}
  </div>;
}

function NotificationPreferencesPanel({ onSaved }) {
  const [preferences, setPreferences] = useState(null);
  useEffect(() => { api('/api/notifications/preferences').then(d => setPreferences(d.preferences)).catch(() => setPreferences(null)); }, []);
  const toggle = async (key) => {
    const next = { ...(preferences || {}), [key]: !preferences?.[key] };
    setPreferences(next);
    await api('/api/notifications/preferences', { method: 'PATCH', body: { [key]: next[key] } });
    onSaved?.('Notification preference saved.');
  };
  const labels = {
    friend_requests: 'Friend requests',
    messages: 'Messages',
    feed: 'Feed reactions and comments',
    runs: 'Friend run alerts',
    quests: 'Quest and reward updates',
  };
  return <Panel eyebrow="Notifications" title="Signal controls">
    <p className="muted">Choose what Orrin should surface in the notification tray and browser alerts.</p>
    {!preferences ? <div className="skeletonCard"><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /></div> : <div className="preferenceList">
      {Object.entries(labels).map(([key, label]) => <button key={key} className={preferences[key] ? 'on' : ''} onClick={() => toggle(key)} type="button">
        <span><strong>{label}</strong><small>{preferences[key] ? 'Enabled' : 'Muted'}</small></span>
        <i />
      </button>)}
    </div>}
  </Panel>;
}

function AdminPlayerEditor({ player, packages, stages, onSaved }) {
  const [draft, setDraft] = useState(null);
  const [tearAmount, setTearAmount] = useState(10);

  useEffect(() => {
    setDraft(player ? {
      is_admin: !!player.is_admin,
      package_id: player.package_id || '',
      lifecycle_stage: player.lifecycle_stage || 'new',
      admin_notes: player.admin_notes || '',
      skill_points: player.skill_points || 0,
    } : null);
  }, [player]);

  if (!player || !draft) {
    return <div className="crmEditor empty"><UserRound size={28} /><strong>Select a player</strong><small>Choose a runner to view account state, notes, package, and support tools.</small></div>;
  }

  const save = async () => {
    const d = await api(`/api/admin/players/${player.id}`, { method: 'PATCH', body: { ...draft, package_id: draft.package_id || null } });
    onSaved?.(d.player);
  };
  const adjustTears = async () => {
    const amount = Number(tearAmount);
    if (!amount) return;
    const d = await api(`/api/admin/players/${player.id}/tears`, { method: 'POST', body: { amount, note: 'Admin CRM adjustment' } });
    onSaved?.(d.player);
    setTearAmount(10);
  };

  return <div className="crmEditor">
    <div className="crmHero">
      <span className="rowAvatar large">{(player.display_name || player.name || '?')[0].toUpperCase()}</span>
      <div><strong>{player.display_name || player.name}</strong><small>{player.email}</small><em>{player.friend_code || 'No friend code'} &middot; {player.total_km}km &middot; {player.total_runs} runs</em></div>
    </div>
    <div className="crmStats">
      <div><b>{player.level}</b><span>Level</span></div>
      <div><b>{player.tears}</b><span>Tears</span></div>
      <div><b>{player.skill_points}</b><span>Skill points</span></div>
    </div>
    <label className="checkLine"><input type="checkbox" checked={draft.is_admin} onChange={e => setDraft({ ...draft, is_admin: e.target.checked })} /> Full admin access</label>
    <div className="split">
      <label>Package<select value={draft.package_id} onChange={e => setDraft({ ...draft, package_id: e.target.value })}><option value="">No package</option>{packages.map(pkg => <option key={pkg.id} value={pkg.id}>{pkg.name}</option>)}</select></label>
      <label>Lifecycle<select value={draft.lifecycle_stage} onChange={e => setDraft({ ...draft, lifecycle_stage: e.target.value })}>{stages.map(stage => <option key={stage} value={stage}>{stage}</option>)}</select></label>
    </div>
    <label>Skill points<input type="number" min="0" max="999" value={draft.skill_points} onChange={e => setDraft({ ...draft, skill_points: Number(e.target.value) })} /></label>
    <label>Admin notes<textarea value={draft.admin_notes} onChange={e => setDraft({ ...draft, admin_notes: e.target.value })} placeholder="Support notes, account state, package context..." /></label>
    <div className="crmActions">
      <button className="primary" onClick={save} type="button">Save player</button>
      <label className="tearAdjust"><Droplet size={14} /><input type="number" value={tearAmount} onChange={e => setTearAmount(e.target.value)} /><button className="ghost" onClick={adjustTears} type="button">Adjust Tears</button></label>
    </div>
  </div>;
}

function AdminPanel() {
  const [stats, setStats] = useState(null);
  const [error, setError] = useState('');
  const [scope, setScope] = useState('all');
  const [regionFilter, setRegionFilter] = useState('all');
  const [packageTab, setPackageTab] = useState(false);
  const [packages, setPackages] = useState(null);
  const [crm, setCrm] = useState({ players: [], packages: [], stages: [] });
  const [crmSearch, setCrmSearch] = useState('');
  const [crmStage, setCrmStage] = useState('all');
  const [crmPackage, setCrmPackage] = useState('all');
  const [selectedPlayer, setSelectedPlayer] = useState(null);
  const loadStats = () => api('/api/admin/stats').then(setStats).catch(err => setError(err.message));
  const loadCrm = useCallback(() => {
    const qs = new URLSearchParams({ search: crmSearch, stage: crmStage, package_id: crmPackage });
    api(`/api/admin/players?${qs}`).then(setCrm).catch(err => setError(err.message));
  }, [crmSearch, crmStage, crmPackage]);
  useEffect(() => { loadStats(); }, []);
  useEffect(() => { loadCrm(); }, [loadCrm]);
  useEffect(() => { if (packageTab) api('/api/admin/packages').then(d => setPackages(d.packages)).catch(() => setPackages(null)); }, [packageTab]);
  if (error) return <Panel eyebrow="Admin" title="Access blocked"><p className="muted">{error}</p></Panel>;
  if (!stats) return <Panel eyebrow="Admin" title="Loading control room"><div className="skeletonCard"><div className="skeleton skeletonLine" /><div className="skeleton skeletonLine short" /><div className="skeleton skeletonBlock" /></div></Panel>;
  const runProgress = Math.min(100, (Number(stats.totals.distance_km || 0) / 100) * 100);
  const questProgress = Math.min(100, (Number(stats.totals.completed_quests || 0) / Math.max(1, Number(stats.quests?.length || 1))) * 100);
  const filteredRegions = stats.regions.filter(region => regionFilter === 'all' || region.difficulty === regionFilter);
  const activePlayers = stats.players.filter(player => scope === 'all' || (scope === 'active' ? player.total_runs > 0 : player.total_runs === 0));
  return <div className="adminGrid newAdmin">
    <section className="adminCommand">
      <div><span className="kicker">Mission control</span><h2>HQ board</h2><p className="muted">Operational view across runners, regions, quests, economy, and live shard signal.</p></div>
      <div className="adminFilters">
        <select value={scope} onChange={e => setScope(e.target.value)}><option value="all">All players</option><option value="active">Active runners</option><option value="quiet">Quiet accounts</option></select>
        <select value={regionFilter} onChange={e => setRegionFilter(e.target.value)}><option value="all">All regions</option><option value="starter">Starter</option><option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option></select>
        <button className={packageTab ? 'primary' : 'ghost'} onClick={() => setPackageTab(!packageTab)}><PackageIcon size={15} />{packageTab ? 'Hide Packages' : 'Packages'}</button>
      </div>
    </section>
    <Panel eyebrow="Control room" title="Trailbound health">
      <div className="adminKpis">
        <PremiumStatCard icon={Users} value={stats.totals.players} label="Players" context={`${stats.totals.active_24h || 0} active today`} progress={Math.min(100, (stats.totals.active_24h || 0) * 20)} />
        <PremiumStatCard icon={Activity} value={stats.totals.runs} label="Runs logged" context={stats.totals.runs ? 'Run ledger is moving' : 'Awaiting first run'} progress={Math.min(100, (stats.totals.runs || 0) * 12)} tone="green" />
        <PremiumStatCard icon={MapPin} value={`${stats.totals.distance_km} km`} label="Shard distance" context={`${Math.max(0, 100 - Number(stats.totals.distance_km || 0)).toFixed(1)} km to 100 km`} progress={runProgress} />
        <PremiumStatCard icon={RadioTower} value={stats.totals.active_24h} label="Active 24h" context={stats.totals.active_24h ? 'Recent player signal' : 'No live signal yet'} progress={Math.min(100, (stats.totals.active_24h || 0) * 25)} tone="blue" />
        <PremiumStatCard icon={Compass} value={stats.totals.completed_quests} label="Quest clears" context={stats.totals.completed_quests ? 'Tasks are converting' : 'No quest clears yet'} progress={questProgress} tone="purple" />
        <PremiumStatCard icon={Globe} value={stats.totals.regions} label="Regions" context="Cape Town shard mesh" progress={Math.min(100, (stats.totals.regions || 0) * 6)} />
      </div>
    </Panel>
    <Panel eyebrow="Economy" title="Tears & items">
      <div className="adminEconGrid">
        <div className="adminEconStat">
          <strong>{stats.totals.total_tears_earned ?? 0}</strong>
          <span>Total Tears earned</span>
        </div>
        <div className="adminEconStat">
          <strong>{stats.totals.total_tears_spent ?? 0}</strong>
          <span>Total Tears spent</span>
        </div>
        <div className="adminEconStat">
          <strong>{stats.totals.items_in_wild ?? 0}</strong>
          <span>Items in circulation</span>
        </div>
        <div className="adminEconStat">
          <strong>{stats.totals.shop_items ?? 0}</strong>
          <span>Active shop items</span>
        </div>
        <div className="adminEconStat">
          <strong>{stats.totals.packages ?? 0}</strong>
          <span>Packages defined</span>
        </div>
        <div className="adminEconStat">
          <strong>{stats.totals.active_challenges ?? 0}</strong>
          <span>Active challenges</span>
        </div>
        <div className="adminEconStat">
          <strong>{stats.totals.referrals ?? 0}</strong>
          <span>Referral links</span>
        </div>
      </div>
    </Panel>
    <Panel eyebrow="Growth" title="Referral signal">
      <div className="adminSplitList">
        <div>
          <strong>Top referrers</strong>
          {(stats.referrals || []).length === 0 ? <p className="muted">No referrals yet.</p> : (stats.referrals || []).map(ref => <div key={ref.id} className="adminMiniRow"><span><b>{ref.name}</b><small>{ref.friend_code || ref.email}</small></span><Chip tone="good">{ref.referrals} invited</Chip></div>)}
        </div>
        <div>
          <strong>Recent joins</strong>
          {(stats.recentReferrals || []).length === 0 ? <p className="muted">Referral trail is quiet.</p> : (stats.recentReferrals || []).map((ref, index) => <div key={index} className="adminMiniRow"><span><b>{ref.child_name}</b><small>via {ref.parent_display_name || ref.parent_name}</small></span><small>{new Date(ref.created_at).toLocaleDateString()}</small></div>)}
        </div>
      </div>
    </Panel>
    <Panel eyebrow="Commercial" title="Packages & challenges">
      <div className="adminSplitList">
        <div>
          <strong>Package mix</strong>
          {(stats.packageMix || []).map(pkg => <div key={pkg.id} className="adminMiniRow"><span><b>{pkg.name}</b><small>{pkg.price_cents ? `R${(pkg.price_cents / 100).toFixed(0)}` : 'Free'} plan</small></span><Chip>{pkg.users} users</Chip></div>)}
        </div>
        <div>
          <strong>Challenge mix</strong>
          {(stats.challengeMix || []).map((item, index) => <div key={index} className="adminMiniRow"><span><b>{item.type}</b><small>{item.status}</small></span><Chip>{item.total}</Chip></div>)}
        </div>
      </div>
    </Panel>
    <Panel eyebrow="Players" title="Latest runners">
      <div className="adminTable premiumRows">{activePlayers.map(player => <div key={player.id} className="playerRow">
        <span className="rowAvatar">{(player.display_name || player.name || '?')[0].toUpperCase()}</span>
        <span><strong>{player.display_name || player.name}</strong><small>{player.email}</small></span>
        <Chip>Lvl {player.level}</Chip>
        <small>{player.friend_code || 'No code'}</small>
        <small><Droplet size={11} /> {player.tears || 0}</small>
        <small>{player.total_km} km</small>
        <small>{player.total_runs} runs</small>
      </div>)}</div>
    </Panel>
    <Panel eyebrow="CRM" title="Player command desk">
      <div className="crmToolbar">
        <label><Search size={14} /><input value={crmSearch} onChange={e => setCrmSearch(e.target.value)} placeholder="Search name, email, code..." /></label>
        <select value={crmStage} onChange={e => setCrmStage(e.target.value)}><option value="all">All stages</option>{(crm.stages || []).map(stage => <option key={stage} value={stage}>{stage}</option>)}</select>
        <select value={crmPackage} onChange={e => setCrmPackage(e.target.value)}><option value="all">All packages</option>{(crm.packages || []).map(pkg => <option key={pkg.id} value={pkg.id}>{pkg.name}</option>)}</select>
      </div>
      <div className="crmGrid">
        <div className="crmList">
          {(crm.players || []).map(player => <button key={player.id} className={`crmPlayer${selectedPlayer?.id === player.id ? ' active' : ''}`} onClick={() => setSelectedPlayer(player)} type="button">
            <span className="rowAvatar">{(player.display_name || player.name || '?')[0].toUpperCase()}</span>
            <span><strong>{player.display_name || player.name}</strong><small>{player.email}</small><em>{player.lifecycle_stage} &middot; {player.package || 'No package'}</em></span>
            <Chip tone={player.is_admin ? 'warm' : 'quiet'}>{player.is_admin ? 'Admin' : `Lvl ${player.level}`}</Chip>
          </button>)}
        </div>
        <AdminPlayerEditor player={selectedPlayer || (crm.players || [])[0]} packages={crm.packages || []} stages={crm.stages || []} onSaved={(player) => { setSelectedPlayer(player); loadCrm(); loadStats(); }} />
      </div>
    </Panel>
    <Panel eyebrow="Regions" title="Shard performance">
      <div className="adminRegionList">{filteredRegions.map(region => <div key={region.id} className="regionPerfRow">
        <span><strong>{region.name}</strong><small>{region.biome} &middot; {region.difficulty}</small></span>
        <Chip tone={region.runs > 2 ? 'good' : region.unlocks ? 'warm' : 'quiet'}>{region.runs > 2 ? 'Active' : region.unlocks ? 'Growing' : 'Quiet'}</Chip>
        <span>{region.runs} runs</span>
        <span>{region.unlocks} unlocks</span>
        <div className="adminMeter"><i style={{ width: `${Math.min(100, region.avg_progress || 0)}%` }} /></div>
      </div>)}</div>
    </Panel>
    <Panel eyebrow="Quests" title="Completion heat">
      <div className="adminQuestList questHeatList">{stats.quests.map(quest => <div key={quest.id} className="questHeatRow">
        <span><strong>{quest.title}</strong><small>{quest.region} &middot; {quest.target_value}km &middot; +{quest.reward_xp} XP</small></span>
        <span className="heatDots" aria-hidden="true">{[0, 1, 2, 3, 4].map(i => <i key={i} className={i < Math.min(5, quest.completions || 0) ? 'on' : ''} />)}</span>
        <Chip tone={quest.completions ? 'good' : 'quiet'}>{quest.completions ? `${quest.completions} clears` : 'No clears'}</Chip>
      </div>)}</div>
    </Panel>
    <Panel eyebrow="Activity" title="Event mix">
      <div className="analyticsIntro"><p className="muted">Activity distribution across the shard. All recorded events, ranked by signal strength.</p><Chip>All activity</Chip></div>
      <EventMixChart items={stats.activity || []} />
    </Panel>
    {packageTab && packages && <Panel eyebrow="Configuration" title="Packages">
      <div className="adminPackageList">
        {packages.map(pkg => (
          <div key={pkg.id} className="adminPackageRow">
            <div>
              <strong>{pkg.name}</strong>
              <small>{pkg.key} &middot; {pkg.price_cents > 0 ? `$${(pkg.price_cents / 100).toFixed(2)}/${pkg.billing_interval || 'mo'}` : 'Free'}</small>
            </div>
            <Chip tone={pkg.is_active ? 'good' : 'quiet'}>{pkg.is_active ? 'Active' : 'Inactive'}</Chip>
            {pkg.is_default && <Chip tone="warm">Default</Chip>}
          </div>
        ))}
      </div>
    </Panel>}
  </div>;
}

function AppShell({ initialUser }) {
  const canvasRef = useRef(null);
  const [user, setUser] = useState(initialUser);
  const [active, setActive] = useState(() => localStorage.getItem('trailbound-active-tab') || 'Dashboard');
  const [world, setWorld] = useState({ regions: [], recent_runs: [] });
  const [run, setRun] = useState({ distance_km: 3, duration_minutes: 24, region_id: '' });
  const [message, setMessage] = useState('');
  const [onlineIds, setOnlineIds] = useState([]);
  const [friends, setFriends] = useState([]);
  const [refreshKey, setRefreshKey] = useState(0);
  const [stravaConnected, setStravaConnected] = useState(false);
  const [stravaLoading, setStravaLoading] = useState(false);
  const [statusText, setStatusText] = useState('');
  const [statusMood, setStatusMood] = useState('');
  const [runImageFiles, setRunImageFiles] = useState(null);
  const [showProfileModal, setShowProfileModal] = useState(false);
  const [mobileMenu, setMobileMenu] = useState(false);
  const [myLocation, setMyLocation] = useState(null);
  const [friendLocations, setFriendLocations] = useState([]);
  const [beacons, setBeacons] = useState([]);
  const [selectedMessageFriend, setSelectedMessageFriend] = useState(null);
  const [theme, setTheme] = useState(() => localStorage.getItem('trailbound-theme') || 'dark');
  const [palette, setPalette] = useState(() => localStorage.getItem('trailbound-palette') || 'trailbound');
  const [notifications, setNotifications] = useState({ unread_count: 0, items: [] });
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [locationStatus, setLocationStatus] = useState('Tap "Use my location" to set your position and unlock your current zone.');
  const [locationLoading, setLocationLoading] = useState(false);
  const [selectedRun, setSelectedRun] = useState(null);
  const [runLoading, setRunLoading] = useState(false);
  const [selectedQuest, setSelectedQuest] = useState(null);
  const [runLogOpen, setRunLogOpen] = useState(false);
  const [eyePhrase, setEyePhrase] = useState('Watching the shard with you.');
  const [tearsBalance, setTearsBalance] = useState(initialUser?.stats?.tears || 0);
  const [showTutorial, setShowTutorial] = useState(!initialUser?.profile?.tutorial_completed_at);
  const [mobileMenuSide, setMobileMenuSide] = useState(initialUser?.profile?.mobile_menu_side || 'right');
  const lastUnreadRef = useRef(0);
  const fileInputRef = useRef(null);
  const lastDiscoveryRef = useRef(null);

  useParticles(canvasRef);
  const shellNav = useMemo(() => user.is_admin ? [...nav, ['Admin', BarChart3]] : nav, [user.is_admin]);
  const ActiveIcon = useMemo(() => shellNav.find(([l]) => l === active)?.[1] || Gauge, [active, shellNav]);
  const unlocked = world.regions.filter(r => r.status !== 'locked');
  const tasks = world.regions.flatMap(r => r.tasks.map(t => ({ ...t, region: r.name, biome: r.biome })));

  useEffect(() => {
    if (!eyePhrase) return;
    const id = setTimeout(() => setEyePhrase(''), 5200);
    return () => clearTimeout(id);
  }, [eyePhrase]);

  useEffect(() => {
    if (shellNav.some(([label]) => label === active)) {
      localStorage.setItem('trailbound-active-tab', active);
    } else {
      setActive('Dashboard');
    }
  }, [active, shellNav]);

  const refreshWorld = useCallback(async () => {
    const d = await api('/api/world');
    setWorld(d);
    if (!run.region_id && d.regions[0]) setRun(c => ({ ...c, region_id: d.regions.find(r => r.status !== 'locked')?.id || d.regions[0].id }));
  }, [run.region_id]);

  const loadNotifications = useCallback(() => {
    api('/api/notifications').then(data => {
      setNotifications(data);
      if (data.unread_count > lastUnreadRef.current && window.Notification?.permission === 'granted') {
        const item = data.items?.[0];
        if (item) new Notification(item.title, { body: item.body, tag: item.id });
      }
      lastUnreadRef.current = data.unread_count || 0;
    }).catch(() => { });
  }, []);

  const loadMapSocial = useCallback(() => {
    api('/api/locations/friends').then(d => {
      setMyLocation(d.me);
      if (d.me?.region?.name) setLocationStatus(`Current zone: ${d.me.region.name}`);
      setFriendLocations(d.friends || []);
    }).catch(() => { });
    api('/api/beacons').then(d => setBeacons(d.beacons || [])).catch(() => { });
  }, []);

  const realtimeConnected = useRealtime(useCallback((event) => {
    window.dispatchEvent(new CustomEvent('trailbound:realtime', { detail: event }));
    if (['map.updated', 'social.updated'].includes(event.type)) {
      loadMapSocial();
      api('/api/online').then(d => setOnlineIds(d.online)).catch(() => { });
      api('/api/friends').then(d => setFriends(d.friends || [])).catch(() => { });
    }
    if (event.type === 'world.updated') {
      refreshWorld();
      loadMapSocial();
    }
    if (event.type === 'messages.updated') {
      loadNotifications();
    }
    if (event.type === 'notifications.updated') {
      loadNotifications();
    }
  }, [loadMapSocial, loadNotifications, refreshWorld]));

  useEffect(() => {
    if (!message) return;
    const id = setTimeout(() => setMessage(''), 5200);
    return () => clearTimeout(id);
  }, [message]);

  useEffect(() => {
    const handler = (event) => setSelectedQuest(event.detail);
    window.addEventListener('trailbound:quest', handler);
    return () => window.removeEventListener('trailbound:quest', handler);
  }, []);

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('trailbound-theme', theme);
  }, [theme]);

  useEffect(() => {
    document.documentElement.dataset.palette = palette;
    localStorage.setItem('trailbound-palette', palette);
  }, [palette]);

  useEffect(() => {
    loadNotifications();
    const id = setInterval(loadNotifications, 60000);
    return () => clearInterval(id);
  }, [loadNotifications]);

  useEffect(() => {
    const poll = () => { api('/api/online/heartbeat', { method: 'POST' }).then(() => api('/api/online')).then(d => setOnlineIds(d.online)).catch(() => { }); };
    poll();
    const hb = setInterval(poll, 60000);
    api('/api/wallet').then(d => setTearsBalance(d.balance)).catch(() => { });
    api('/api/strava/status').then(d => setStravaConnected(d.connected)).catch(() => { });
    api('/api/friends').then(d => setFriends(d.friends || [])).catch(() => { });
    return () => clearInterval(hb);
  }, []);

  useEffect(() => {
    loadMapSocial();
    const id = setInterval(loadMapSocial, 60000);
    return () => clearInterval(id);
  }, [refreshKey, loadMapSocial]);

  const applyLocationResponse = useCallback(async (d, explicit = false) => {
    setMyLocation(d.location);
    if (d.location?.region?.id) {
      setRun(current => current.region_id ? current : { ...current, region_id: d.location.region.id });
    }
    if (d.discovery?.region_id && lastDiscoveryRef.current !== d.discovery.region_id) {
      lastDiscoveryRef.current = d.discovery.region_id;
      await refreshWorld();
      setMessage(`Location logged. ${d.location?.region?.name || 'This zone'} is now unlocked.`);
    } else if (explicit) {
      setMessage(d.location?.region?.name ? `Location logged in ${d.location.region.name}.` : 'Location logged. You are outside the current Cape Town zones.');
    }
    setLocationStatus(d.location?.region?.name ? `Current zone: ${d.location.region.name}` : 'Current location saved outside the Cape Town shard.');
  }, []);

  const postCurrentPosition = useCallback((pos, explicit = false) => {
    const body = {
      lat: pos.coords.latitude,
      lng: pos.coords.longitude,
      accuracy_m: Math.round(pos.coords.accuracy || 0),
      share_mode: 'friends',
    };
    return api('/api/location/heartbeat', { method: 'POST', body }).then(d => applyLocationResponse(d, explicit));
  }, [applyLocationResponse]);

  const requestAndLogLocation = useCallback(async () => {
    if (!navigator.geolocation) {
      setLocationStatus('This browser does not support location access.');
      setMessage('This browser does not support location access.');
      return;
    }
    setLocationLoading(true);
    setLocationStatus('Waiting for location permission...');
    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        try {
          await postCurrentPosition(pos, true);
        } catch (err) {
          setMessage(err.message);
          setLocationStatus('Could not save your current location.');
        } finally {
          setLocationLoading(false);
        }
      },
      (err) => {
        const denied = err.code === err.PERMISSION_DENIED;
        setLocationStatus(denied ? 'Location permission is blocked. Enable it in your browser to unlock zones.' : 'Could not read your location. Try again in a clearer GPS spot.');
        setMessage(denied ? 'Location permission is blocked. Enable it in your browser settings.' : 'Could not read your location.');
        setLocationLoading(false);
      },
      { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 }
    );
  }, [postCurrentPosition]);

  useEffect(() => {
    if (!navigator.geolocation) {
      setLocationStatus('This browser does not support location access.');
      return;
    }
    let watcher = null;
    navigator.permissions?.query({ name: 'geolocation' }).then(permission => {
      if (permission.state === 'granted') {
        setLocationStatus('Location access enabled. Updating your current zone.');
        watcher = navigator.geolocation.watchPosition((pos) => {
          postCurrentPosition(pos).catch(() => { });
        }, () => { }, { enableHighAccuracy: true, maximumAge: 30000, timeout: 12000 });
      } else if (permission.state === 'prompt') {
        setLocationStatus('Tap "Use my location" to set your position and unlock your current zone.');
      } else {
        setLocationStatus('Location permission is blocked. Enable it in your browser to unlock zones.');
      }
    }).catch(() => { });
    return () => { if (watcher !== null) navigator.geolocation.clearWatch(watcher); };
  }, [postCurrentPosition]);

  /*
  useEffect(() => {
    if (!navigator.geolocation) return;
    const watcher = navigator.geolocation.watchPosition((pos) => {
      const body = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy_m: Math.round(pos.coords.accuracy || 0),
        share_mode: 'friends',
      };
      api('/api/location/heartbeat', { method: 'POST', body }).then(d => {
        setMyLocation(d.location);
        if (d.discovery?.region_id && lastDiscoveryRef.current !== d.discovery.region_id) {
          lastDiscoveryRef.current = d.discovery.region_id;
          refreshWorld();
        }
      }).catch(() => { });
    }, () => { }, { enableHighAccuracy: true, maximumAge: 30000, timeout: 12000 });
    return () => navigator.geolocation.clearWatch(watcher);
  }, []);
  */

  useEffect(() => { refreshWorld(); }, [refreshWorld]);

  const logRun = async (e) => { e.preventDefault(); setMessage(''); const d = await api('/api/runs', { method: 'POST', body: run }); setMessage(`Run logged. ${d.run.xp_awarded} XP awarded.`); setEyePhrase(`Nice run, ${user.profile?.display_name || user.name}. +${d.run.xp_awarded} XP banked.`); if (runImageFiles) { const fd = new FormData(); Array.from(runImageFiles).forEach(f => fd.append('images[]', f)); await api(`/api/runs/${d.run.id}/images`, { method: 'POST', body: fd }); setRunImageFiles(null); if (fileInputRef.current) fileInputRef.current.value = ''; } const me = await api('/api/auth/me'); setUser(me.user); await refreshWorld(); setRefreshKey(k => k + 1); setRunLogOpen(false); };
  const saveProfile = async (p) => { const d = await api('/api/profile', { method: 'PATCH', body: p }); setUser(d.user); await refreshWorld(); setMessage('Profile saved.'); };
  const completeTutorial = async () => {
    setShowTutorial(false);
    try {
      const d = await api('/api/profile', {
        method: 'PATCH',
        body: {
          display_name: user.profile?.display_name || user.name,
          home_area: user.profile?.home_area || 'City Bowl',
          runner_type: user.profile?.runner_type || 'Pathfinder',
          weekly_goal_km: user.profile?.weekly_goal_km || 15,
          privacy_level: user.profile?.privacy_level || 'private',
          mobile_menu_side: mobileMenuSide,
          tutorial_completed_at: new Date().toISOString(),
        },
      });
      setUser(d.user);
    } catch { }
  };
  const updateMobileSide = async (side) => {
    setMobileMenuSide(side);
    const d = await api('/api/profile', {
      method: 'PATCH',
      body: {
        display_name: user.profile?.display_name || user.name,
        home_area: user.profile?.home_area || 'City Bowl',
        runner_type: user.profile?.runner_type || 'Pathfinder',
        weekly_goal_km: user.profile?.weekly_goal_km || 15,
        privacy_level: user.profile?.privacy_level || 'private',
        mobile_menu_side: side,
      },
    });
    setUser(d.user);
    setMessage(`Mobile menu moved to the ${side}.`);
  };
  const uploadAvatar = async (file) => { const fd = new FormData(); fd.append('avatar', file); try { const d = await api('/api/profile/avatar', { method: 'POST', body: fd }); setUser(d.user); setMessage('Avatar updated.'); } catch (err) { alert(err.message); } };
  const uploadBackground = async (file) => { const fd = new FormData(); fd.append('background', file); setMessage('Uploading profile cover...'); try { const d = await api('/api/profile/background', { method: 'POST', body: fd }); setUser(d.user); setMessage('Profile cover updated.'); } catch (err) { setMessage(err.message); } };
  const updateBio = async (bio) => { try { const d = await api('/api/profile/bio', { method: 'PATCH', body: { bio } }); setUser(d.user); setMessage('Bio saved.'); } catch (err) { alert(err.message); } };
  const postStatus = async (e) => { e.preventDefault(); try { await api('/api/status', { method: 'POST', body: { status_text: statusText, mood: statusMood || null } }); setStatusText(''); setStatusMood(''); setMessage('Status posted.'); setEyePhrase('I saw that. Mood logged, signal clean.'); setRefreshKey(k => k + 1); } catch (err) { alert(err.message); } };
  const connectStrava = async () => { setStravaLoading(true); try { const d = await api('/api/strava/connect'); window.open(d.url, '_blank', 'width=600,height=700'); setMessage('Authorize Strava, then paste the code param from the redirect URL.'); } catch (err) { setMessage(err.message); } finally { setStravaLoading(false); } };
  const handleStravaCallback = async (code) => { setStravaLoading(true); try { const d = await api('/api/strava/callback', { method: 'POST', body: { code } }); setStravaConnected(true); setMessage(`Strava connected! Imported ${d.sync?.imported || 0} runs.`); setRefreshKey(k => k + 1); refreshWorld(); } catch (err) { setMessage(err.message); } finally { setStravaLoading(false); } };
  const syncStrava = async () => { setStravaLoading(true); try { const d = await api('/api/strava/sync', { method: 'POST' }); setMessage(`Synced! +${d.imported} new runs.`); setRefreshKey(k => k + 1); refreshWorld(); const me = await api('/api/auth/me'); setUser(me.user); } catch (err) { setMessage(err.message); } finally { setStravaLoading(false); } };
  const disconnectStrava = async () => { await api('/api/strava/disconnect', { method: 'POST' }); setStravaConnected(false); setMessage('Strava disconnected.'); };
  const dropBeacon = async (center) => {
    const base = myLocation || (center ? { lat: center.lat, lng: center.lng } : null);
    if (!base) { setMessage('Enable location first, or wait for the map to settle, then drop a beacon.'); return; }
    const title = prompt('Beacon title', 'Rally at this shard');
    if (!title) return;
    const note = prompt('Optional note', 'Easy pace. Come run this zone.');
    const data = await api('/api/beacons', { method: 'POST', body: { lat: base.lat, lng: base.lng, title, note, kind: 'rally', region_id: myLocation?.region?.id || null } });
    setBeacons(current => [data.beacon, ...current]);
    setMessage('Rally beacon dropped. Friends can see it on the shard map.');
    setRefreshKey(k => k + 1);
  };
  const messageFriendFromMap = async (friendId) => {
    setSelectedMessageFriend(friendId);
    setActive('Messages');
  };
  const openRunDashboard = async (runId) => {
    setRunLoading(true);
    setSelectedRun(null);
    try {
      const data = await api(`/api/runs/${runId}`);
      setSelectedRun(data.run);
    } catch (err) {
      setMessage(err.message);
    } finally {
      setRunLoading(false);
    }
  };
  const navigateFromNotification = (target) => {
    if (target) setActive(target);
    setNotificationsOpen(false);
  };
  const enableBrowserNotifications = async () => {
    if (!('Notification' in window)) { setMessage('Browser notifications are not supported here.'); return; }
    const permission = await Notification.requestPermission();
    setMessage(permission === 'granted' ? 'Browser notifications enabled.' : 'Browser notifications were not enabled.');
  };
  const logout = async () => { await api('/api/auth/logout', { method: 'POST' }); window.location.reload(); };

  const isOnline = true;

  return <>
    <canvas ref={canvasRef} className="particles" aria-hidden="true" />
    {mobileMenu && <button className="mobileNavScrim" aria-label="Close menu" onClick={() => setMobileMenu(false)} />}
    <button className={`mobileEyeToggle ${mobileMenuSide}${mobileMenu ? ' open' : ''}`} onClick={() => setMobileMenu(!mobileMenu)} aria-label={mobileMenu ? 'Close navigation' : 'Open navigation'}>
      {mobileMenu ? <X size={24} /> : <Eye small />}
    </button>

    {showTutorial && <TutorialModal onClose={completeTutorial} />}
    {showProfileModal && <ProfileModal user={user} onlineIds={onlineIds} onClose={() => setShowProfileModal(false)} refreshKey={refreshKey} />}
    {(runLoading || selectedRun) && <RunDashboardModal run={selectedRun} loading={runLoading} onClose={() => { setSelectedRun(null); setRunLoading(false); }} />}
    {runLogOpen && <RunLogModal run={run} setRun={setRun} world={world} fileInputRef={fileInputRef} setRunImageFiles={setRunImageFiles} onSubmit={logRun} onClose={() => setRunLogOpen(false)} />}
    {selectedQuest && <QuestModal quest={selectedQuest} onClose={() => setSelectedQuest(null)} />}

    <main className="shell">
      <aside className={`sidebar${mobileMenu ? ' open' : ''}`}>
        <a className="brand" href="/" onClick={e => e.preventDefault()}><BrandLogo compact /></a>
        <div className="sbProfile">
          <label className="sbAvatar" title="View profile">
            <div className="sbAvInner" onClick={() => setShowProfileModal(true)}>
              {user.profile?.avatar_path ? <img src={user.profile.avatar_path} alt="" /> : <UserRound size={22} />}
            </div>
            <input type="file" accept="image/*" onChange={e => { if (e.target.files[0]) uploadAvatar(e.target.files[0]); }} hidden />
            <span className="sbCamera"><Camera size={10} /></span>
            <span className={`sbOnline ${isOnline ? 'on' : 'off'}`} />
          </label>
          <div>
            <strong style={{ cursor: 'pointer' }} onClick={() => setShowProfileModal(true)}>{user.profile?.display_name || user.name}</strong>
            <small>Lvl {user.stats.level} &middot; <Droplet size={10} style={{ display: 'inline', verticalAlign: 'middle' }} />{tearsBalance}</small>
          </div>
        </div>
        <nav>{shellNav.map(([label, Icon]) => <button key={label} className={active === label ? 'active' : ''} onClick={() => { setActive(label); setMobileMenu(false); }}><Icon size={17} />{label}{label === 'Social' && onlineIds.length > 0 && <span className="badge">{onlineIds.length}</span>}</button>)}</nav>
        <button className="logoutBtn" onClick={logout}><LogOut size={15} />Logout</button>
        <div className="sbStatus"><span />{user.profile?.home_area || 'Cape Town'} shard &middot; {isOnline ? 'online' : 'active'}</div>
      </aside>

      <section className={`content ${active !== 'Dashboard' ? 'compactContent' : ''}`}>
        <div className="topDock">
          <button className="iconBtn" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')} title={`${theme === 'dark' ? 'Light' : 'Dark'} mode`}>
            {theme === 'dark' ? <Sun size={17} /> : <Moon size={17} />}
          </button>
          <NotificationCenter data={notifications} open={notificationsOpen} onToggle={() => setNotificationsOpen(v => !v)} onNavigate={navigateFromNotification} onEnableBrowser={enableBrowserNotifications} onRefresh={loadNotifications} />
        </div>
        {active === 'Dashboard' && <header className="hero">
          <div className="heroText">
            <p className="kicker">Logged in as {user.profile?.display_name || user.name}</p>
            <h1>Run. Reveal. Rule.</h1>
            <p>Cape Town is fenced into living regions. Your home area sets your starting biome, runs push progress, and tasks unlock as your real-world effort stacks up.</p>
            <div className="actions">
              <button className="primary" onClick={() => { setActive('Runs'); setRunLogOpen(true); }}>Log run</button>
              <button className="ghostBtn" onClick={() => setActive('Cape Town')}>Open map</button>
            </div>
          </div>
          <Eye phrase={eyePhrase} onCharm={() => setEyePhrase('Orrin is with you. Always watching the route.')} />
        </header>}

        <div className="contentInner" key={active}>
        <div className="sectionBar"><ActiveIcon size={22} /><span>{active}</span></div>
        {message && <p className="notice">{message}</p>}

        <form className="statusBar" onSubmit={postStatus}>
          <Smile size={16} className="statusIcon" />
          <input value={statusText} onChange={e => setStatusText(e.target.value)} placeholder="What's your status, runner?" maxLength={300} />
          <select value={statusMood} onChange={e => setStatusMood(e.target.value)}><option value="">Mood</option><option value="charged">⚡ Charged</option><option value="focused">🎯 Focused</option><option value="grinding">🔥 Grinding</option><option value="recovering">🌙 Recovering</option><option value="exploring">🧭 Exploring</option></select>
          <button className="primary" type="submit" disabled={!statusText.trim()}>Post</button>
        </form>

        {active === 'Dashboard' && <div className="grid dash">
          <Panel eyebrow="Location" title={myLocation?.region?.name || 'Set your current zone'}><p className="muted">{locationStatus}</p><button className="primary" onClick={requestAndLogLocation} disabled={locationLoading}><MapPin size={15} />{locationLoading ? 'Checking location...' : myLocation ? 'Log current location' : 'Use my location'}</button></Panel>
          <Panel eyebrow="Wallet" title={`${tearsBalance} Tears`}><div className="tearsBalanceCard"><Droplet size={32} className="tearsBigIcon" /><div><b>{tearsBalance}</b><span>Tears balance</span></div></div><p className="muted">Earn Tears from quests and challenges. Spend them in the Shop or Skill Tree.</p><button className="ghost" onClick={() => setActive('Shop')}><ShoppingBag size={14} /> Visit Shop</button></Panel>
          <Panel eyebrow="Character" title={`Level ${user.stats.level}`}><div className="statRow"><div><b>{user.stats.xp} XP</b><span>total earned</span></div><div><b>{user.stats.total_km} km</b><span>distance</span></div><div><b>{user.stats.runs} runs</b><span>completed</span></div></div><div className="xpBar"><i style={{ width: `${Math.min(100, (user.stats.xp % 500) / 5)}%` }} /></div><small className="xpLabel">{user.stats.xp % 500} / 500 XP to next level</small></Panel>
          <Panel eyebrow="World state" title={`${unlocked.length} / ${world.regions.length} regions`}><p className="muted">{unlocked[0]?.name || 'Starter region'} is your foothold. New regions unlock from task chains and distance milestones.</p></Panel>
          <Panel eyebrow="Goals" title={user.profile?.runner_type || 'Runner'}><div className="statRow"><div><b>{user.profile?.weekly_goal_km || 0} km</b><span>weekly goal</span></div><div><b>{user.profile?.home_area || 'N/A'}</b><span>home area</span></div></div></Panel>
          <Panel eyebrow="Strava" title={stravaConnected ? 'Connected' : 'Not Connected'}>{stravaConnected ? <div className="stravaActions"><p className="muted">Strava is connected. Sync to import your latest runs.</p><div className="actions"><button className="primary" onClick={syncStrava} disabled={stravaLoading}>{stravaLoading ? 'Syncing...' : 'Sync from Strava'}</button><button className="ghostBtn" onClick={disconnectStrava}>Disconnect</button></div></div> : <div className="stravaActions"><p className="muted">Connect Strava to auto-import your runs.</p><button className="primary" onClick={connectStrava} disabled={stravaLoading}><Cable size={15} /> Connect Strava</button><label className="stravaCode">Paste callback `code` param<input placeholder="Paste code from redirect URL" onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); handleStravaCallback(e.target.value); } }} /></label></div>}</Panel>
        </div>}

        {active === 'Cape Town' && <WorldMap regions={world.regions} friends={friends} profile={user.profile} myLocation={myLocation} friendLocations={friendLocations} beacons={beacons} onDropBeacon={dropBeacon} onMessageFriend={messageFriendFromMap} onLogLocation={requestAndLogLocation} locationStatus={locationStatus} locationLoading={locationLoading} realtimeConnected={realtimeConnected} />}

        {active === 'Progress' && <ProgressPanel user={user} world={world} tasks={tasks} onNavigate={setActive} />}

        {active === 'Tasks' && <Panel eyebrow="Quest board" title={`${tasks.filter(t => t.status !== 'locked').length} active quests`}>{tasks.filter(t => t.status !== 'locked').length === 0 ? <div className="emptyState"><Compass size={28} /><p>No tasks available. Start logging runs in your home region.</p></div> : <div className="questBoard">{tasks.filter(t => t.status !== 'locked').map(t => { const progress = t.status === 'complete' ? 100 : t.status === 'in_progress' ? 58 : 18; return <button key={t.id} className={`questCard clickable ${t.status}`} onClick={() => setSelectedQuest(t)}><div className="questCardTop"><span>{t.region}</span><b>+{t.reward_xp} XP</b></div><strong>{t.title}</strong><small>{t.biome} &middot; {t.unlock_rule}</small><div className="questProgress"><i style={{ width: `${progress}%` }} /></div><div className="questCardFoot"><span>{t.status.replace('_', ' ')}</span><span>{progress}%</span></div></button>; })}</div>}</Panel>}

        {active === 'Runs' && <div className="runsView">
          <div className="runsSummary">
            <PremiumStatCard icon={Activity} value={`${user.stats.total_km} km`} label="Total distance" context={`${user.stats.runs} logged runs`} progress={Math.min(100, user.stats.total_km * 3)} />
            <PremiumStatCard icon={Gauge} value={world.recent_runs?.[0] ? `${Number(world.recent_runs[0].distance_km).toFixed(1)} km` : 'No run'} label="Latest effort" context={world.recent_runs?.[0] ? new Date(world.recent_runs[0].run_at).toLocaleDateString() : 'Awaiting first run'} progress={world.recent_runs?.[0] ? 70 : 5} />
            <PremiumStatCard icon={Compass} value={unlocked.length} label="Regions open" context={`${world.regions.length} Cape Town zones`} progress={world.regions.length ? (unlocked.length / world.regions.length) * 100 : 0} />
          </div>
          <Panel eyebrow="History" title={`${world.recent_runs?.length || 0} recent runs`}><div className="panelActions"><button className="primary" onClick={() => setRunLogOpen(true)}>Log a run</button></div>{(world.recent_runs || []).length === 0 ? <div className="emptyState"><Activity size={28} /><p>No runs yet. Log one above or sync from Strava.</p></div> : <div className="taskList runHistoryList">{(world.recent_runs || []).map(r => <button key={r.id} className="taskItem clickable runHistoryItem" onClick={() => openRunDashboard(r.id)}><div><strong>{r.distance_km} km</strong><small>{r.duration_minutes} min &middot; {new Date(r.run_at).toLocaleDateString()}</small></div><div className="taskMeta"><span>{r.source}</span><b>+{r.xp_awarded} XP</b></div>{r.image_paths?.length > 0 && <div className="runImgs">{r.image_paths.map((p, i) => <img key={i} src={p} alt="" />)}</div>}</button>)}</div>}</Panel>
          <Panel eyebrow="Map memory" title="Run signals"><RunRouteMap runs={world.recent_runs || []} regions={world.regions} /></Panel>
        </div>}

        {active === 'Social' && <SocialPanel user={user} onlineIds={onlineIds} refreshKey={refreshKey} onOpenRun={openRunDashboard} setMessage={setMessage} friends={friends} />}
        {active === 'Messages' && <MessagesPanel friends={friends} selectedFriendId={selectedMessageFriend} onHandled={() => setSelectedMessageFriend(null)} refreshKey={refreshKey} user={user} />}

        {active === 'Shop' && <ShopPanel refreshKey={refreshKey} onBalanceUpdate={setTearsBalance} user={user} />}
        {active === 'Inventory' && <InventoryPanel refreshKey={refreshKey} />}
        {active === 'Skill Tree' && <SkillTreePanel refreshKey={refreshKey} />}
        {active === 'Challenges' && <ChallengesPanel refreshKey={refreshKey} friends={friends} user={user} />}

        {active === 'Help' && <HelpPanel onReplayTutorial={() => setShowTutorial(true)} />}

        {active === 'Profile' && <div className="grid">
          <ProfileHeroCard user={user} onAvatar={uploadAvatar} onBackground={uploadBackground} />
          <PackageInfoPanel user={user} />
          <FriendCodeCard user={user} onMessage={setMessage} />
          <BadgeGallery refreshKey={refreshKey} />
          <AppearancePanel theme={theme} setTheme={setTheme} palette={palette} setPalette={setPalette} />
          <Panel eyebrow="Bio" title="About you"><form onSubmit={e => { e.preventDefault(); updateBio(e.target.bio.value); }}><textarea name="bio" defaultValue={user.profile?.bio || ''} rows={3} maxLength={500} placeholder="Tell other runners about yourself..." /><button className="primary" type="submit">Save bio</button></form></Panel>
          <ProfileEditor user={user} onSave={saveProfile} />
        </div>}

        {active === 'Settings' && <div className="grid">
          <AppearancePanel theme={theme} setTheme={setTheme} palette={palette} setPalette={setPalette} />
          <Panel eyebrow="Runner type" title={user.profile?.runner_type || 'Pathfinder'}><div className="classGrid">{runnerClasses.map(c => <button key={c.id} className={user.profile?.runner_type === c.id ? 'primary' : 'ghost'} onClick={() => saveProfile({ display_name: user.profile?.display_name || user.name, home_area: user.profile?.home_area || 'City Bowl', runner_type: c.id, weekly_goal_km: user.profile?.weekly_goal_km || 15, privacy_level: user.profile?.privacy_level || 'private', mobile_menu_side: mobileMenuSide })}>{c.title}</button>)}</div></Panel>
          <NotificationPreferencesPanel onSaved={setMessage} />
          <Panel eyebrow="Accessibility" title="Mobile controls"><p className="muted">Move the floating Orrin menu toggle to the side that is easiest to reach.</p><div className="themeSwitch"><button className={mobileMenuSide === 'left' ? 'active' : ''} onClick={() => updateMobileSide('left')} type="button">Left</button><button className={mobileMenuSide === 'right' ? 'active' : ''} onClick={() => updateMobileSide('right')} type="button">Right</button></div><button className="ghost" type="button" onClick={() => setShowTutorial(true)}><BookOpen size={15} />Replay tutorial</button></Panel>
          <Panel eyebrow="Strava" title={stravaConnected ? 'Connected' : 'Disconnected'}><p className="muted">Route visibility defaults to private.</p>{stravaConnected ? <button className="ghostBtn" onClick={disconnectStrava}>Disconnect Strava</button> : <button className="primary" onClick={connectStrava} disabled={stravaLoading}><Cable size={15} /> Connect Strava</button>}</Panel>
          <Panel eyebrow="Danger zone" title="Data"><p className="muted">You can disconnect Strava at any time. Imported activities remain in your history.</p></Panel>
        </div>}
        {active === 'Admin' && user.is_admin && <AdminPanel />}
        </div>
      </section>
    </main>
  </>;
}

function ProfileEditor({ user, onSave }) {
  const [profile, setProfile] = useState({ display_name: user.profile?.display_name || user.name, home_area: user.profile?.home_area || 'City Bowl', runner_type: user.profile?.runner_type || 'Pathfinder', weekly_goal_km: user.profile?.weekly_goal_km || 15, privacy_level: user.profile?.privacy_level || 'private', mobile_menu_side: user.profile?.mobile_menu_side || 'right' });
  return <Panel eyebrow="Full profile suite" title="Runner identity"><form className="form" onSubmit={e => { e.preventDefault(); onSave(profile); }}><label>Display name<input value={profile.display_name} onChange={e => setProfile({ ...profile, display_name: e.target.value })} /></label><label>Home shard<input value={profile.home_area} onChange={e => setProfile({ ...profile, home_area: e.target.value })} /></label><div className="split"><label>Runner class<select value={profile.runner_type} onChange={e => setProfile({ ...profile, runner_type: e.target.value })}>{runnerClasses.map(c => <option key={c.id}>{c.id}</option>)}</select></label><label>Weekly goal km<input type="number" value={profile.weekly_goal_km} onChange={e => setProfile({ ...profile, weekly_goal_km: Number(e.target.value) })} /></label></div><label>Privacy<select value={profile.privacy_level} onChange={e => setProfile({ ...profile, privacy_level: e.target.value })}><option value="private">Private</option><option value="friends">Friends</option><option value="public">Public</option></select></label><button className="primary">Save profile</button></form></Panel>;
}

function Root() {
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState(null);
  useEffect(() => {
    document.documentElement.dataset.theme = localStorage.getItem('trailbound-theme') || 'dark';
    document.documentElement.dataset.palette = localStorage.getItem('trailbound-palette') || 'trailbound';
  }, []);
  useEffect(() => { api('/api/auth/me').then(d => setUser(d.user)).catch(() => setUser(null)).finally(() => setLoading(false)); }, []);
  if (loading) return <div className="loading"><Eye /><div>Trailbound is waking up</div></div>;
  return user ? <AppShell initialUser={user} /> : <AuthGate onAuthed={setUser} />;
}

createRoot(document.getElementById('app')).render(<Root />);
