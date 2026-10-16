<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Numerisation Carte des parcelles</title>

    <!-- Leaflet en local (comme dans votre projet).
         Variante sans dossier local :
         https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css et leaflet.js -->
    <link rel="stylesheet" href="leaflet/dist/leaflet.css" />

    <style>
        :root {
            --ink: #17302f;
            --muted: #5d7270;
            --panel: #f7f9f8;
            --line: #d3dcda;
            --accent: #0f6b6b;
            --accent-dark: #0a4f4f;
            --warn: #b45309;
            --error: #b42318;
            --ok: #1a7f4b;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: "Segoe UI", system-ui, -apple-system, sans-serif; color: var(--ink); font-size: 14px; }

        .app { display: grid; grid-template-columns: 370px 1fr; height: 100vh; }
        #panneau { background: var(--panel); border-right: 1px solid var(--line); overflow-y: auto; padding: 16px; }
        #map { height: 100%; width: 100%; background: #e9eeed; }

        .leaflet-tile, #map img { max-width: none !important; max-height: none !important; }

        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 0 0 8px; }
        section { border-top: 1px solid var(--line); padding: 14px 0; }
        section:first-of-type { border-top: 0; }

        .statut { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 13px; }
        .point { width: 9px; height: 9px; border-radius: 50%; background: var(--muted); flex: none; }
        .point.ok { background: var(--ok); }
        .point.erreur { background: var(--error); }

        label { display: flex; align-items: center; gap: 6px; }
        select, input[type="text"], input:not([type]) {
            font: inherit; color: inherit; padding: 6px 8px; border: 1px solid var(--line);
            border-radius: 6px; background: #fff; min-width: 0;
        }
        button {
            font: inherit; padding: 6px 12px; border-radius: 6px; cursor: pointer;
            border: 1px solid var(--accent); background: #fff; color: var(--accent);
        }
        button.principal { background: var(--accent); color: #fff; }
        button.principal:hover { background: var(--accent-dark); }
        button:hover { background: #eaf3f2; }
        button.principal:hover { background: var(--accent-dark); }
        :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

        .ligne { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 8px; }

        .cond { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 8px; padding: 8px; background: #fff; border: 1px solid var(--line); border-radius: 8px; }
        .cond .c-val { grid-column: 1 / 2; }
        .cond .c-sup { grid-column: 2 / 3; justify-self: end; padding: 6px 10px; border-color: var(--line); color: var(--muted); }

        .modes { display: flex; gap: 16px; margin-top: 8px; }

        #resume { color: var(--muted); margin-bottom: 8px; }
        .tableau { max-height: 280px; overflow: auto; border: 1px solid var(--line); border-radius: 8px; background: #fff; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #edf1f0; white-space: nowrap; }
        th { position: sticky; top: 0; background: #eef3f2; }
        tbody tr { cursor: pointer; }
        tbody tr:hover { background: #fff6e0; }

        .etiquette-parcelle {
            background: transparent; border: 0; box-shadow: none;
            color: #8a1c1c; font-weight: 700; font-size: 13px;
            text-shadow: 1px 1px 2px #fff, -1px -1px 2px #fff, 1px -1px 2px #fff, -1px 1px 2px #fff;
        }
        .etiquette-parcelle::before { display: none; }

        .popup-table td { padding: 2px 8px 2px 0; border: 0; }
        .popup-table td:first-child { font-weight: 600; color: var(--muted); }

        #toast {
            position: absolute; left: 50%; bottom: 28px; transform: translateX(-50%);
            background: var(--ink); color: #fff; padding: 8px 14px; border-radius: 8px;
            z-index: 1000; opacity: 0; pointer-events: none; transition: opacity .25s;
        }
        #toast.visible { opacity: 1; }
        .carte-zone { position: relative; min-height: 0; }

        @media (max-width: 800px) {
            .app { grid-template-columns: 1fr; grid-template-rows: 46vh 1fr; }
            #panneau { border-right: 0; border-bottom: 1px solid var(--line); }
        }
        @media (prefers-reduced-motion: reduce) { #toast { transition: none; } }
    </style>
</head>
<body>
<div class="app">

    <aside id="panneau">
        <h1>Conception d’un système cadastral numérique</h1>
        <div class="statut"><span id="point" class="point"></span><span id="statut">Chargement…</span></div>

        <section>
            <h2>Synchronisation avec QGIS</h2>
            <div class="ligne">
                <label><input type="checkbox" id="auto" checked> Actualisation automatique</label>
            </div>
            <div class="ligne">
                <label>Toutes les
                    <select id="intervalle">
                        <option value="2000">2 s</option>
                        <option value="5000" selected>5 s</option>
                        <option value="10000">10 s</option>
                        <option value="30000">30 s</option>
                    </select>
                </label>
                <button type="button" id="btnActualiser">Actualiser maintenant</button>
            </div>
        </section>

        <section>
            <h2>Requête attributaire</h2>
            <div id="conditions"></div>
            <div class="ligne">
                <button type="button" id="btnAjouter">Ajouter une condition</button>
                <label id="blocLogique" hidden>Combiner avec
                    <select id="logique">
                        <option value="et">ET (toutes)</option>
                        <option value="ou">OU (au moins une)</option>
                    </select>
                </label>
            </div>
            <div class="modes">
                <label><input type="radio" name="mode" value="surligner" checked> Surligner</label>
                <label><input type="radio" name="mode" value="filtrer"> Ne montrer que les résultats</label>
            </div>
            <div class="ligne">
                <button type="button" id="btnRechercher" class="principal">Rechercher</button>
                <button type="button" id="btnEffacer">Effacer la requête</button>
            </div>
        </section>

        <section>
            <h2>Résultats</h2>
            <div id="resume">Aucune requête en cours.</div>
            <div class="ligne" style="margin: 0 0 8px;">
                <button type="button" id="btnZoomer" disabled>Zoomer sur les résultats</button>
                <button type="button" id="btnCsv" disabled>Exporter en CSV</button>
            </div>
            <div class="tableau" id="zoneTableau" hidden>
                <table>
                    <thead id="tHead"></thead>
                    <tbody id="tBody"></tbody>
                </table>
            </div>
        </section>

        <section>
            <h2>Affichage</h2>
            <div class="ligne">
                <label><input type="checkbox" id="afficherEtiq" checked> Afficher les étiquettes</label>
            </div>
            <div class="ligne">
                <label>Champ de l'étiquette <select id="champEtiq"></select></label>
            </div>
            <div class="ligne">
                <button type="button" id="btnRecentrer">Recentrer sur la couche</button>
            </div>
        </section>
    </aside>

    <div class="carte-zone">
        <div id="map"></div>
        <div id="toast" role="status" aria-live="polite"></div>
    </div>
</div>

<script src="leaflet/dist/leaflet.js"></script>
<script>
/* ============================================================
   CONFIGURATION
   ============================================================ */
const CONFIG = {
    fichier: 'parcelle9.geojson',   // fichier écrit par QGIS (EPSG:4326)
    champEtiquette: 'PL',           // champ affiché en étiquette par défaut
    centre: [-11.6609, 27.4794],    // Lubumbashi (avant le zoom automatique)
    zoom: 12,
    maxLignesTableau: 200
};

const OPERATEURS = [
    ['eq', '='], ['ne', '≠'], ['contient', 'contient'], ['commence', 'commence par'],
    ['termine', 'se termine par'], ['gt', '>'], ['gte', '≥'], ['lt', '<'], ['lte', '≤'],
    ['vide', 'est vide'], ['nonvide', "n'est pas vide"]
];

const STYLE_BASE = { color: '#0f6b6b', weight: 2, opacity: 0.9, fillColor: '#4aa3a3', fillOpacity: 0.25 };
const STYLE_SELECTION = { color: '#b45309', weight: 3, opacity: 1, fillColor: '#fbbf24', fillOpacity: 0.55 };

/* ============================================================
   CARTE
   ============================================================ */
const map = L.map('map').setView(CONFIG.centre, CONFIG.zoom);

const fondPlan = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors', maxZoom: 19
}).addTo(map);
const fondSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Imagery © Esri', maxZoom: 19
});
L.control.layers({ 'Plan': fondPlan, 'Satellite': fondSatellite }).addTo(map);
L.control.scale({ imperial: false }).addTo(map);

/* ============================================================
   ÉTAT
   ============================================================ */
const etat = {
    texte: null,            // contenu brut du dernier fichier lu (détection des changements)
    data: null,             // FeatureCollection
    couche: null,           // couche Leaflet courante
    calques: new Map(),     // index entité -> calque Leaflet
    champs: [],             // noms des champs attributaires
    champEtiquette: CONFIG.champEtiquette,
    requete: null,          // {conds, logique, mode} ou null
    resultats: new Set(),   // index des entités qui satisfont la requête
    premiere: true,
    minuteur: null
};

const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ============================================================
   CHARGEMENT ET SYNCHRONISATION
   ============================================================ */
function normaliser(d) {
    if (d.type === 'Feature') d = { type: 'FeatureCollection', features: [d] };
    d.features = (d.features || []).filter(f => f && f.geometry);
    d.features.forEach((f, i) => { f.properties = f.properties || {}; f._i = i; });
    return d;
}

function majStatut(texte, classe) {
    $('statut').textContent = texte;
    $('point').className = 'point ' + (classe || '');
}

function heure() { return new Date().toLocaleTimeString('fr-FR'); }

async function charger() {
    try {
        const r = await fetch(CONFIG.fichier + '?t=' + Date.now(), { cache: 'no-store' });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const texte = await r.text();
        if (texte === etat.texte) {                      // rien n'a changé
            majStatut('À jour (vérifié à ' + heure() + ')', 'ok');
            return;
        }
        const data = normaliser(JSON.parse(texte));      // peut échouer si QGIS écrit encore le fichier
        const deja = etat.texte !== null;
        const avant = etat.data ? etat.data.features.length : 0;

        etat.texte = texte;
        etat.data = data;
        etat.champs = collecterChamps(data);
        majListesChamps();
        recalculer();

        if (etat.premiere) { recentrer(); etat.premiere = false; }
        majStatut('Synchronisé à ' + heure() + ' · ' + data.features.length + ' entités', 'ok');
        if (deja) {
            const diff = data.features.length - avant;
            const detail = diff === 0 ? '' : (diff > 0 ? ' (+' + diff + ')' : ' (' + diff + ')');
            toast('Couche mise à jour' + detail);
        }
    } catch (e) {
        console.error(e);
        majStatut('Lecture impossible : ' + e.message + '. Nouvel essai bientôt.', 'erreur');
    }
}

function planifier() {
    clearInterval(etat.minuteur);
    if ($('auto').checked) etat.minuteur = setInterval(charger, Number($('intervalle').value));
}

function collecterChamps(data) {
    const s = new Set();
    data.features.forEach(f => Object.keys(f.properties).forEach(k => s.add(k)));
    return [...s];
}

function toast(msg) {
    const t = $('toast');
    t.textContent = msg;
    t.classList.add('visible');
    clearTimeout(toast.h);
    toast.h = setTimeout(() => t.classList.remove('visible'), 2500);
}

/* ============================================================
   COUCHE
   ============================================================ */
function construireCouche() {
    if (etat.couche) map.removeLayer(etat.couche);
    etat.calques.clear();

    const filtrer = etat.requete && etat.requete.mode === 'filtrer';
    const etiquettes = $('afficherEtiq').checked;

    etat.couche = L.geoJSON(etat.data, {
        filter: f => !filtrer || etat.resultats.has(f._i),
        style: f => (etat.requete && etat.resultats.has(f._i)) ? STYLE_SELECTION : STYLE_BASE,
        onEachFeature: (f, calque) => {
            etat.calques.set(f._i, calque);
            calque.bindPopup(() => popupHtml(f.properties), { maxHeight: 260 });
            const lib = f.properties[etat.champEtiquette];
            if (etiquettes && !estVide(lib)) {
                calque.bindTooltip(String(lib), { permanent: true, direction: 'center', className: 'etiquette-parcelle' });
            }
        }
    }).addTo(map);
}

function popupHtml(props) {
    const lignes = Object.entries(props).map(([k, v]) => '<tr><td>' + esc(k) + '</td><td>' + esc(v) + '</td></tr>').join('');
    return '<table class="popup-table">' + lignes + '</table>';
}

function recentrer() {
    if (etat.couche && etat.couche.getBounds().isValid()) map.fitBounds(etat.couche.getBounds(), { padding: [30, 30] });
}

function limitesDe(calque) {
    return calque.getBounds ? calque.getBounds() : L.latLngBounds([calque.getLatLng()]);
}

/* ============================================================
   REQUÊTES ATTRIBUTAIRES
   ============================================================ */
const estVide = v => v === null || v === undefined || String(v).trim() === '';
const nombre = v => (typeof v === 'number') ? v : (estVide(v) ? NaN : Number(String(v).replace(',', '.')));

function teste(props, c) {
    const v = props[c.champ];
    if (c.op === 'vide') return estVide(v);
    if (c.op === 'nonvide') return !estVide(v);
    if (estVide(v)) return c.op === 'ne';

    const s = String(v).toLowerCase(), t = c.valeur.toLowerCase();
    const nv = nombre(v), nt = nombre(c.valeur);
    const num = !isNaN(nv) && !isNaN(nt);

    switch (c.op) {
        case 'eq':       return num ? nv === nt : s === t;
        case 'ne':       return num ? nv !== nt : s !== t;
        case 'contient': return s.includes(t);
        case 'commence': return s.startsWith(t);
        case 'termine':  return s.endsWith(t);
        case 'gt':       return num ? nv > nt : s > t;
        case 'gte':      return num ? nv >= nt : s >= t;
        case 'lt':       return num ? nv < nt : s < t;
        case 'lte':      return num ? nv <= nt : s <= t;
    }
    return false;
}

function lireConditions() {
    return [...document.querySelectorAll('.cond')].map(div => ({
        champ: div.querySelector('.c-champ').value,
        op: div.querySelector('.c-op').value,
        valeur: div.querySelector('.c-val').value.trim()
    })).filter(c => c.champ && (c.op === 'vide' || c.op === 'nonvide' || c.valeur !== ''));
}

function lancerRequete() {
    const conds = lireConditions();
    if (!conds.length) { effacerRequete(); toast('Renseignez au moins une condition'); return; }
    etat.requete = {
        conds,
        logique: $('logique').value,
        mode: document.querySelector('input[name="mode"]:checked').value
    };
    recalculer();
    if (etat.resultats.size) zoomerResultats();
}

function effacerRequete() {
    etat.requete = null;
    etat.resultats = new Set();
    document.querySelectorAll('.cond .c-val').forEach(i => i.value = '');
    recalculer();
}

// Recalcule les résultats puis redessine : appelé après chaque requête ET après chaque rechargement du fichier.
function recalculer() {
    etat.resultats = new Set();
    if (etat.requete && etat.data) {
        const { conds, logique } = etat.requete;
        etat.data.features.forEach(f => {
            const ok = logique === 'et' ? conds.every(c => teste(f.properties, c)) : conds.some(c => teste(f.properties, c));
            if (ok) etat.resultats.add(f._i);
        });
    }
    construireCouche();
    afficherResultats();
}

function afficherResultats() {
    const total = etat.data ? etat.data.features.length : 0;
    const zone = $('zoneTableau');

    if (!etat.requete) {
        $('resume').textContent = total ? total + ' entités dans la couche.' : 'Aucune donnée chargée.';
        zone.hidden = true;
        $('btnZoomer').disabled = $('btnCsv').disabled = true;
        return;
    }

    const n = etat.resultats.size;
    $('resume').textContent = n + ' résultat' + (n > 1 ? 's' : '') + ' sur ' + total + ' entités.';
    $('btnZoomer').disabled = $('btnCsv').disabled = (n === 0);
    zone.hidden = (n === 0);

    const cols = [etat.champEtiquette, ...etat.champs.filter(c => c !== etat.champEtiquette).slice(0, 2)].filter(c => etat.champs.includes(c));
    $('tHead').innerHTML = '<tr>' + cols.map(c => '<th>' + esc(c) + '</th>').join('') + '</tr>';

    const lignes = [];
    for (const i of etat.resultats) {
        if (lignes.length >= CONFIG.maxLignesTableau) break;
        const p = etat.data.features[i].properties;
        lignes.push('<tr data-i="' + i + '">' + cols.map(c => '<td>' + esc(p[c]) + '</td>').join('') + '</tr>');
    }
    $('tBody').innerHTML = lignes.join('');
    if (n > CONFIG.maxLignesTableau) toast('Tableau limité aux ' + CONFIG.maxLignesTableau + ' premières lignes');
}

function zoomerResultats() {
    const groupe = L.featureGroup([...etat.resultats].map(i => etat.calques.get(i)).filter(Boolean));
    if (groupe.getLayers().length && groupe.getBounds().isValid()) map.fitBounds(groupe.getBounds(), { padding: [40, 40], maxZoom: 19 });
}

function exporterCsv() {
    const sep = ';';
    const cell = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const lignes = [etat.champs.map(cell).join(sep)];
    for (const i of etat.resultats) {
        const p = etat.data.features[i].properties;
        lignes.push(etat.champs.map(c => cell(p[c])).join(sep));
    }
    const blob = new Blob(['\ufeff' + lignes.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'resultats_requete.csv';
    a.click();
    URL.revokeObjectURL(a.href);
}

/* ============================================================
   INTERFACE : lignes de condition
   ============================================================ */
let compteur = 0;

function optionsChamps(selection) {
    return etat.champs.map(c => '<option value="' + esc(c) + '"' + (c === selection ? ' selected' : '') + '>' + esc(c) + '</option>').join('');
}

function ajouterCondition() {
    const id = ++compteur;
    const div = document.createElement('div');
    div.className = 'cond';
    div.innerHTML =
        '<select class="c-champ" aria-label="Champ">' + optionsChamps() + '</select>' +
        '<select class="c-op" aria-label="Opérateur">' + OPERATEURS.map(o => '<option value="' + o[0] + '">' + o[1] + '</option>').join('') + '</select>' +
        '<input class="c-val" list="dl' + id + '" placeholder="Valeur" aria-label="Valeur">' +
        '<datalist id="dl' + id + '"></datalist>' +
        '<button type="button" class="c-sup" aria-label="Supprimer la condition">Supprimer</button>';
    $('conditions').appendChild(div);

    div.querySelector('.c-champ').addEventListener('change', () => remplirSuggestions(div));
    div.querySelector('.c-op').addEventListener('change', e => {
        const sansValeur = e.target.value === 'vide' || e.target.value === 'nonvide';
        div.querySelector('.c-val').disabled = sansValeur;
    });
    div.querySelector('.c-val').addEventListener('keydown', e => { if (e.key === 'Enter') lancerRequete(); });
    div.querySelector('.c-sup').addEventListener('click', () => {
        if (document.querySelectorAll('.cond').length > 1) div.remove();
        else div.querySelector('.c-val').value = '';
        $('blocLogique').hidden = document.querySelectorAll('.cond').length < 2;
    });

    remplirSuggestions(div);
    $('blocLogique').hidden = document.querySelectorAll('.cond').length < 2;
}

// Propose les valeurs existantes du champ choisi (jusqu'à 200 valeurs distinctes)
function remplirSuggestions(div) {
    const champ = div.querySelector('.c-champ').value;
    const vals = new Set();
    if (etat.data) {
        for (const f of etat.data.features) {
            const v = f.properties[champ];
            if (!estVide(v)) vals.add(String(v));
            if (vals.size >= 200) break;
        }
    }
    div.querySelector('datalist').innerHTML = [...vals].sort().map(v => '<option value="' + esc(v) + '">').join('');
}

// Met à jour les listes de champs après un rechargement, sans perdre les choix en cours
function majListesChamps() {
    document.querySelectorAll('.cond').forEach(div => {
        const sel = div.querySelector('.c-champ');
        const ancien = sel.value;
        sel.innerHTML = optionsChamps(ancien);
        remplirSuggestions(div);
    });
    const sel = $('champEtiq');
    const voulu = etat.champs.includes(etat.champEtiquette) ? etat.champEtiquette
                : (etat.champs.includes(CONFIG.champEtiquette) ? CONFIG.champEtiquette : etat.champs[0]);
    etat.champEtiquette = voulu;
    sel.innerHTML = optionsChamps(voulu);
}

/* ============================================================
   ÉVÉNEMENTS
   ============================================================ */
$('auto').addEventListener('change', planifier);
$('intervalle').addEventListener('change', planifier);
$('btnActualiser').addEventListener('click', charger);
$('btnAjouter').addEventListener('click', ajouterCondition);
$('btnRechercher').addEventListener('click', lancerRequete);
$('btnEffacer').addEventListener('click', effacerRequete);
$('btnZoomer').addEventListener('click', zoomerResultats);
$('btnCsv').addEventListener('click', exporterCsv);
$('btnRecentrer').addEventListener('click', recentrer);
$('afficherEtiq').addEventListener('change', () => { construireCouche(); });
$('champEtiq').addEventListener('change', e => { etat.champEtiquette = e.target.value; recalculer(); });
document.querySelectorAll('input[name="mode"]').forEach(r => r.addEventListener('change', () => {
    if (etat.requete) { etat.requete.mode = r.value; recalculer(); }
}));

// Clic sur une ligne du tableau : zoom sur la parcelle et ouverture de ses attributs
$('tBody').addEventListener('click', e => {
    const tr = e.target.closest('tr');
    if (!tr) return;
    const calque = etat.calques.get(Number(tr.dataset.i));
    if (!calque) return;
    map.fitBounds(limitesDe(calque), { maxZoom: 19, padding: [60, 60] });
    calque.openPopup();
});

/* ============================================================
   DÉMARRAGE
   ============================================================ */
ajouterCondition();
charger().then(planifier);
setTimeout(() => map.invalidateSize(), 300);
</script>
</body>
</html>