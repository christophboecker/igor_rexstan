const Rexstan = {

    flagSearchApplied: 'rexstan-search-applied',
    flagSearchHit: 'rexstan-search-hit',
    htmlSearchHighlight: '<mark>$1</mark>',
    sessionPrefix: 'REXSTANFILE',
    sessionSettingsTabset: 'REXSTAN-SETTINGS_TABSET',
    evtSearch: 'rexstan:search',
    evtHeadersize: 'rexstan:headersize',
    evtToggleCollapse: 'rexstan:toggleCollapse',
    evtRefresh: 'rexstan:refresh',
    evtCountTotal: 'rexstan:count.total',
    initialCollapse: '1',
    searchKey4Tips: '^',

    event: function (node, eventName, options = {}) {
        let defaultOptions = {
            bubbles: true,
            cancelable: true,
        };
        node.dispatchEvent(new CustomEvent(eventName, Object.assign(defaultOptions, options)));
    },

    // aus core/standard.js adaptiert --->
    showLoader: function (rexAjaxLoaderId) {
        // show loader
        // show only if page takes longer than 200 ms to load
        window.clearTimeout(rexAjaxLoaderId);
        rexAjaxLoaderId = setTimeout(function () {
            document.documentElement.style.overflowY = 'hidden'; // freeze scroll position
            document.querySelector('#rex-js-ajax-loader').classList.add('rex-visible');
        }, 200);
        return rexAjaxLoaderId;
    },

    hideLoader: function (rexAjaxLoaderId) {
        // hide loader
        // make sure loader was visible for at least 500 ms to avoid flickering
        window.clearTimeout(rexAjaxLoaderId);
        rexAjaxLoaderId = setTimeout(function () {
            document.querySelector('#rex-js-ajax-loader').classList.remove('rex-visible');
            document.documentElement.style.overflowY = null;
        }, 500);
        return rexAjaxLoaderId;
    },
    // <---

    /**
     * ein Workaround um sicherzustellen, dass alle Sub-Elemente geladen sind, bevor
     * weitere Arbeitschritte im CustomHTML loslegen. Erübrigt in den meisten Fällen
     * Hilfskrücken mit DOMContentLoaded, wenn es um die Nodes IM Element geht
     * Quelle: https://stackoverflow.com/questions/48498581/textcontent-empty-in-connectedcallback-of-a-custom-htmlelement
     */
    BaseElement: (superclass) => class extends superclass {

        constructor(...args) {
            const self = super(...args);
            self.parsed = false; // guard to make it easy to do certain stuff only once
            self.parentNodes = [];
            return self
        }

        connectedCallback() {
            if (typeof super.connectedCallback === "function") {
                super.connectedCallback();
            }
            // --> HTMLBaseElement
            // when connectedCallback has fired, call super.setup()
            // which will determine when it is safe to call childrenAvailableCallback()
            this.setup()
        }

        childrenAvailableCallback() {
        }

        setup() {
            // collect the parentNodes
            let el = this;
            while (el.parentNode) {
                el = el.parentNode
                this.parentNodes.push(el)
            }
            // check if the parser has already passed the end tag of the component
            // in which case this element, or one of its parents, should have a nextSibling
            // if not (no whitespace at all between tags and no nextElementSiblings either)
            // resort to DOMContentLoaded or load having triggered
            if ([this, ...this.parentNodes].some(el => el.nextSibling) || document.readyState !== 'loading') {
                this.parsed = true;
                if (this.mutationObserver) this.mutationObserver.disconnect();
                this.childrenAvailableCallback();
            } else {
                this.mutationObserver = new MutationObserver((mutationList) => {
                    if ([this, ...this.parentNodes].some(el => el.nextSibling) || document.readyState !== 'loading') {
                        this.childrenAvailableCallback()
                        this.mutationObserver.disconnect()
                    }
                });

                // Wegen Problemen in der Erkennung mehrere aufeinander folgender gleicher CustomHtmlElemente
                // geändert auf this.parentNode. Umd schon funktioniert es.
                if (this.mutationObserver) this.mutationObserver.observe(this.parentNode, { childList: true });
            }
        }
    },

    /**
     * HTMLElement zur Anzeige von Zählergebnisse (z.B. als Badges), die nach konfigurierbaren Regeln DOM-Elemente zählen 
     * 
     *  <rexstan-amount attributes></rexstan-amount>
     * 
     *      target="«selector»"                 sucht den Node, in dem amount zählen soll. Typischer querySelector-Qualifier.
     *                                          Wenn der String mit << beginnt, wird mit dem ersten Element ein closest(...) durchgeführt.
     *                                          und vondort aus nach ':scope rest' gegangen.
     *      [filter="«selector»"]               was soll am/im Target gezählt werden
     *      [pattern="«string_mit_#"]           Anzeige (default ='#', also nur Anzahl)
     *      [options=«MutationObserverOptions»] https://developer.mozilla.org/en-US/docs/Web/API/MutationObserver/observe
     *                                          als JSON-String. Default: '{"childList":true}' => { childList: true }
     *      [zero]                              auch Null-Werte anzeigen
     *      [force="event"]                     Name eines Events. Durch Absenden des Events wird amount dazu veranlasst,
     *                                          neu zu zählen. amount lauscht auf document.
     *      [event="«event-id»"]                Zusätzlichen Event-Qualifier (amout.changed.«event-id»)
     * 
     * optische Formatierung über Klassen (z.B. ".badge" oder ".label .label-«style»")
     *
     * Feuert bei Änderungen den Event 'amount.changed' ab. Liefert Details in evt.detail.
     * - evt.detail.old = alter Zählerstand
     * - evt.detail.new = neuer Zählerstand
     *
     * Beispiele:
     * <rexstan-amount target"#abc" filter=":scope > .xyz" pattern="ok=#" class="badge"></rexstan-amount>
     * <rexstan-amount target"<<.panel > div" filter=":scope > .xyz" pattern="ok=#" class="badge"></rexstan-amount>
     */
    Amount: class extends HTMLElement {

        value = 0
        __target = ''
        __targetNode = null
        __targetMatch = null
        __filter = ':scope > *'
        __observer = null
        __observerOptions = { childList: true }
        __eventId = 'amount.changed'
        __forceEvent = null
        __pattern = '#'
        __countActive = false

        static get observedAttributes() {
            return ['target', 'filter', 'pattern', 'options', 'force', 'event'];
        }

        /**
         * Target-Node ist ein zuverlässiger Marker
         */
        attributeChangedCallback(name, oldValue, newValue) {
            switch (name) {
                case 'target':
                    this.__targetNode = null;
                    this.__target = newValue ? (newValue.trim() || '') : '';
                    if (this.__target) {
                        this.__targetMatch = this.__target.match(/^(\<\<)?([#.]?[\w-]+)([\s\<\>~].*)?$/);
                        if (!this.__targetMatch) {
                            console.error(`Invalid formed attribute \`target="${this.__target}"\``);
                        }
                    }
                    // NOTE: __targetNode selbst kann beim ersten Aufruf noch nicht ermittelt werden (mit 99% Wahrscheinlichkeit noch nicht im DOM) 
                    break;
                case 'filter':
                    this.__filter = newValue ? (newValue.trim() || ':scope > *') : ':scope > *';
                    this._countElements();
                    break;
                case 'pattern':
                    this.__pattern = newValue ? (newValue.trim() || '#') : '#';
                    this._showResult();
                    break;
                case 'options':
                    try {
                        this.__observerOptions = JSON.parse(newValue);
                    } catch (e) {
                        this.__observerOptions = { childList: true };
                    }
                    this._startObserver();
                    break;
                case 'force':
                    this._startForceListener(newValue ? (newValue.trim() || null) : null);
                    break;
                case 'event':
                    let event = this.getAttribute('event').trim() || '';
                    event = '' === event ? event : `.${event}`;
                    this.__eventId = `amount.changed${event}`;
                    break;
                default:
                    break;
            }
        }

        get zero() {
            return this.hasAttribute('zero') ? 'zero' : '';
        }

        connectedCallback() {
            this.innerHTML = '';
            document.addEventListener('DOMContentLoaded', this._DOMContentLoaded.bind(this));
        }

        disconnectedCallback() {
            this.__observer.disconnect();
            document.removeEventListener(this.__forceEvent, this._countElements.bind(this));
        }

        _DOMContentLoaded() {
            this._startObserver();
            this._startForceListener();
        }

        _startObserver() {
            if (this.__observer) {
                this.__observer.disconnect();
            } else {
                this.__observer = new MutationObserver(this._countElements.bind(this));
            }
            if (this._getTargetNode()) {
                this.__observer.observe(this._getTargetNode(), this.__observerOptions);
                this._countElements();
            }
        }

        _startForceListener(forceEvent) {
            document.removeEventListener(this.__forceEvent, this._countElements.bind(this));
            this.__forceEvent = forceEvent;
            if (this.__forceEvent) {
                document.addEventListener(this.__forceEvent, this._countElements.bind(this));
            }
        }

        _getTargetNode() {
            if (this.__targetNode) {
                return this.__targetNode;
            }
            if (this.__targetMatch[1]) {
                this.__targetNode = this.closest(this.__targetMatch[2])
                if (this.__targetNode && this.__targetMatch[3]) {
                    this.__targetNode = this.__targetNode.querySelector(':scope ' + this.__targetMatch[3].trim());
                }
            } else {
                this.__targetNode = document.querySelector(this.__target);
            }
            if (!this.__targetNode) {
                console.warn('Invalid attribute `target="' + this.__target + '"`, search failed');
            }
            return this.__targetNode;
        }

        _countElements() {
            if( !this.__targetNode ) {
                return;
            }
            if (this.__countActive) return;
            this.__countActive = true;
            let oldValue = this.value;
            try {
                this.value = this.__targetNode.querySelectorAll(this.__filter).length;
            } catch (e) {
                this.value = 0;
            }
            this._showResult(oldValue);
            this.__countActive = false;
        }

        _showResult(oldValue=null) {
            this.innerHTML = this.value || this.zero ? this.__pattern.replace('#', this.value) : '';
            if (null !== oldValue && oldValue !== this.value) {
                Rexstan.event(this, this.__eventId, { detail: { old: oldValue, new: this.value } });
            }
        }
    },

    /**
     * HTMLElement für Buttons, die einfach einen Custom Event absetzen
     * 
     *  <rexstan-trigger attributes></rexstan-trigger>
     * 
     *      [from="«selector»"]                 Node-Qualifiere, auf dem der Event abgeschickt werden soll
     *                                          default/Fallback: dieser DOM-Node
     *                                          Wenn der String mit << beginnt, wird mir dem ersten Element 
     *                                          ein closet(...) durchgeführt um vom Ziel nach ':scope rest' durchgeführt.
     *      event="«event-name»"                wie heißt der Event
     *      [detail="«data»"]                   Mit dem Event in event.detail verschickte Daten
     *
     * Beispiel:
     * <rexstan-trigger class="button" from="<<.panel" event="rexstan:collapse" detail="show"><i class="rex-icon rex-icon-view"></i></rexstan-trigger>
     */
    Trigger: class extends HTMLElement {

        __node = this
        __from = ''
        __event = null
        __detail = ''

        connectedCallback() {
            this.addEventListener('click', this._onClick.bind(this));
        }

        disconnectedCallback() {
            this.removeEventListener('click', this._onClick.bind(this));
        }

        static get observedAttributes() {
            return ['from', 'event', 'detail'];
        }

        attributeChangedCallback(name, oldValue, newValue) {
            if (oldValue == newValue) {
                return;
            }
            if ('from' == name) {
                this.__node = null;
                this.__from = newValue;
                return;
            }
            if ('event' == name) {
                if ('' == newValue) {
                    this.__event = null;
                } else {
                    this.__event = newValue
                }
                return;
            }
            if ('detail' == name) {
                try {
                    this.__detail = JSON.parse(newValue);
                } catch (e) {
                    this.__detail = newValue;
                }
                return;
            }
        }

        _onClick(event) {
            event.stopPropagation();
            if (null == this.__event) {
                return console.error(`${this.tagName}: Missing event-name; feature disabled`);
            }
            Rexstan.event(this._node(), this.__event, { detail: this.__detail });
        }

        _node() {
            if( this.__node ) {
                return this.__node;
            }
            if ('' < this.__from) {
                try {
                    let match = this.__from.match(/^(\<\<)?([#.]?[\w-]+)([\s\<\>~].*)?$/);
                    if (!match) {
                        throw `Invalid formed attribute 'from="${this.__from}"'`;
                    }
                    if (match[1]) {
                        this.__node = this.closest(match[2]);
                        if (this.__node && match[3]) {
                            this.__node = this.__node.querySelector(':scope ' + match[3].trim());
                        }
                    } else {
                        this.__node = document.querySelector(this.__from);
                    }
                    if (!this.__node) {
                        throw `Invalid attribute 'from="${this.__from}"', target not found`;
                    }
                } catch (error) {
                    console.warn(`${this.tagName}: ${error}; replaced by this node`)
                    this.__node = this;
                }
            }
            return this.__node;
        }
    },

    /**
     * HTMLElement für Sucheingaben (analog zu Fragment core/form/search)
     * 
     *  <rexstan-search></rexstan-search>
     * 
     * Dieses HTML-Element interagiert über Events
     * 
     * rexstan:search       jede Änderung des Eingebefeldes (Suchbegriff) wird mit diesem Event mitgeteilt. 
     *                      Weitere Informationen stehen in event.detail: 
     *                          event.detail.search         der aktuelle Wert des Eingabefeldes (Suchbegriff)
     *                          event.detail.hasSearch      false wenn search leer ist
     *                          event.detail.pattern        Regex-Pattern; im Suchbegriff sind die RegEx-Sonderzeichen escaped
     *                          event.detail.regex          RegExp-Objekt für den Suchbegriff (`(${pattern})`, 'gi')
     * 
     * rexstan:search.set   Damit wird von außen der Suchbegriff gesetzt (event.detail='text') oder gelöscht (event.detail='').
     *                      Die Änderung löst wiederum einen rexstan:search-Event aus.
     * 
     * Der aktuelle Wert annüber einen Event 
     */
    Search: class extends HTMLElement {

        __resetBtn = null
        __input = null

        // Element wurde ins DOM eingehängt
        connectedCallback() {
            this.classList.add('input-group');
            this.classList.add('input-group-xs');
            this.classList.add('has-feedback');
            this.classList.add('form-clear-button');
            this.insertAdjacentHTML('afterbegin',
                '<span class="input-group-addon clear-button"><i class="rex-icon rex-icon-search"></i></span>' +
                '<input class="form-control" type="text" placeholder="Suchen...">' +
                '<span title="Eingabe zurücksetzen" class="form-control-clear rex-icon rex-icon-clear form-control-feedback hidden"></span>'
            );
            this.__input = this.querySelector('input');
            this.__resetBtn = this.querySelector('.form-control-clear');

            this.__input.addEventListener('input', this._onInput.bind(this));
            this.__resetBtn.addEventListener('click', this._onResetInput.bind(this));
            document.addEventListener(Rexstan.evtSearch + '.set', this._onSetInput.bind(this));
            document.addEventListener(Rexstan.evtSearch + '.query', this._query.bind(this));
        }

        // Element wurde entfernt
        disconnectedCallback() {
            this.__input.removeEventListener('input', this._onInput.bind(this));
            this.__resetBtn.removeEventListener('click', this._onResetInput.bind(this));
            document.removeEventListener(Rexstan.evtSearch + '.set', this._onSetInput.bind(this));
        }

        _onInput(event) {
            this.__resetBtn.classList.toggle('hidden', '' == this.__input.value);
            this._notify();
        }

        _onSetInput(event) {
            this.__input.value = event.detail;
            this._notify();
        }

        _onResetInput(event) {
            event.stopPropagation();
            this.__input.value = '';
            this.__resetBtn.classList.add('hidden');
            this._notify();
        }

        _notification() {
            let term = this.__input.value.toLowerCase();
            let pattern = term.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
            return {
                search: term,
                hasSearch: term.length > 0,
                pattern: pattern,
                regex: new RegExp(`(${pattern})`, 'gi'),
            }
        }

        _notify() {
            Rexstan.event(this, Rexstan.evtSearch, {
                detail: this._notification(),
            });
        }

        // TODO: Sendet Ergebnis als Callback an event.detail wenn angegeben
        _query(event) {
            this._notify();
        }
    },

    /**
     * Löst das Feuerwerk aus, wenn Level 9 erfolgreich absolviert wurde.
     */
    hipHipHurray: function() {
        let duration = 10 * 1000;
        let animationEnd = Date.now() + duration;
        let defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 0 };

        function randomInRange(min, max) {
          return Math.random() * (max - min) + min;
        }

        let interval = setInterval(function() {
            let timeLeft = animationEnd - Date.now();

            if (timeLeft <= 0) {
                return clearInterval(interval);
            }

            let particleCount = 50 * (timeLeft / duration);
            // since particles fall down, start a bit higher than random
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
            confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
        }, 250);

    },
}

customElements.define('rexstan-amount', Rexstan.Amount);
customElements.define('rexstan-search', Rexstan.Search);
customElements.define('rexstan-trigger', Rexstan.Trigger);

/** <rexstan-analysis> Klammer um die Analyse-Ergebnisse
 * 
 * Dieses CustomHTML führt mehrere autonome Aktionen durch:
 *  - Nachdem die Messages-Knoten initialisiert sind, werden alle Session-Einträge
 *    zu nicht mehr vorhendenen Dateien (Messages-Knoten) gelöscht
 *  - Setzt einen ResizeObserver auf, der Höhenänderungen des Haupt-Headers erkennt
 *    und per Event rexstan:headersize den Messages-Knoten übermittelt
 */
customElements.define('rexstan-analysis',
    class extends Rexstan.BaseElement(HTMLElement) {

        childrenAvailableCallback() {
            this.parsed = true;
            let sections = Array.from(this.querySelectorAll(':scope > rexstan-messages'));
            let headerNode = this.firstElementChild;

            // sessionKeys nicht mehr vorhandener Dateien löschen
            let sessionKeys = Object.keys(sessionStorage).filter(item => item.startsWith(Rexstan.sessionPrefix));
            sections.forEach(node => sessionKeys = sessionKeys.filter(item => item !== node.__sessionName));
            sessionKeys.forEach(key => sessionStorage.removeItem(key));

            // Handler für Höhenänderung des Headers
            // sticky Header im Dateiblock muss unter dem sticky Haupt-Header stoppen
            // Änderungen per Event propagieren
            let height = 0;
            let myObserver = new ResizeObserver(entries => {
                entries.forEach(entry => {
                    if (height != entry.contentRect.height) {
                        height = entry.contentRect.height;
                        Rexstan.event(headerNode, Rexstan.evtHeadersize, { detail: entry });
                    }
                });
            });
            myObserver.observe(headerNode);
        }
    }
);

/** <rexstan-messages> Bündelt die einzelnen Message-Einträge einer Datei.
 * 
 * Dieses CustomHTML führt mehrere autonome Aktionen durch:
 *  - Bei Änderungen im Suchfeld wird die Klasse .rexstan-search-applied gesetzt
 *    (über Event rexstan:search)
 *  - Bei Änderungen der Header-Höhe des Haupt-Header (der mit dem Suchfeld) muss der
 *    Stopp-Punkt des eigenen Headers angepasst werden 
 *    (über Event rexstan:headersize)
 *  - Blendet die Messages ein oder aus (Bootstrap-collapse) wenn angefordert
 *    (über Event rexstan:collapse)
 *  - speichert den aktuellen Collapse-Status in der Session
 *    (über Bootstrap-Events getriggert)
 *  - Setzt initial den Collapse-Status aus der Session (falls dort vorhanden)
 *    Aufblenden wenn neu, also nicht vorhanden (gem. Rexstan.initialCollapse)
 */
customElements.define('rexstan-messages',
    class extends Rexstan.BaseElement(HTMLElement) {

        __sessionName = ''
        __headerNode = null
        __root = null
        __collapseNode = null
        __rexAjaxLoaderId = null

        connectedCallback() {
            this.__sessionName = `${Rexstan.sessionPrefix}${this.dataset.name}`;
            this.__root = this.closest('rexstan-analysis');
            if (!this.__root) {
                console.error('Container "<rexstan-analysis>" not found; features disabled', this);
                return;
            }
            super.connectedCallback();
        }

        childrenAvailableCallback() {
            this.parsed = true;
            this.__root.addEventListener(Rexstan.evtSearch, this._applySearch.bind(this));
            this.__root.addEventListener(Rexstan.evtHeadersize, this._applyStickyOffset.bind(this));
            this.__root.addEventListener(Rexstan.evtToggleCollapse, this._toggleCollapse.bind(this));
            this.addEventListener(Rexstan.evtRefresh, this._refresh.bind(this));
            $(this).on('shown.bs.collapse', this._collapseShown.bind(this));
            $(this).on('hidden.bs.collapse', this._collapseHidden.bind(this));
            this.__headerNode = this.querySelector(':scope > .panel > header[data-target]');
            this.__collapseNode = this.querySelector(this.__headerNode.dataset.target);
            let session = sessionStorage.getItem(this.__sessionName) || Rexstan.initialCollapse;
            let visibility = ('1' == session) ? '1' : '0';
            $(this.__collapseNode).collapse(1 == visibility ? 'show' : 'hide');
        }

        disconnectedCallback() {
            this.__root.removeEventListener(Rexstan.evtSearch, this._applySearch.bind(this));
            this.__root.removeEventListener(Rexstan.evtHeadersize, this._applyStickyOffset.bind(this));
            this.__root.removeEventListener(Rexstan.evtToggleCollapse, this._toggleCollapse.bind(this));
            this.removeEventListener(Rexstan.evtRefresh, this._refresh.bind(this));
            $(this).off('shown.bs.collapse', this._collapseShown.bind(this));
            $(this).off('hidden.bs.collapse', this._collapseHidden.bind(this));
        }

        _applySearch(event) {
            this.classList.toggle(Rexstan.flagSearchApplied, event.detail.hasSearch);
        }

        _collapseShown() {
            sessionStorage.setItem(this.__sessionName, '1');
        }

        _collapseHidden() {
            sessionStorage.setItem(this.__sessionName, '0');
        }

        _applyStickyOffset(event) {
            this.__headerNode.style.top = `${event.detail.contentRect.height}px`;
        }

        _toggleCollapse(event) {
            $(this.__collapseNode).collapse(event.detail)
        }

        _refresh(event) {
            this.__isRefresh = true;
            this.__rexAjaxLoaderId = Rexstan.showLoader(this.__rexAjaxLoaderId);
            fetch(`${window.location.href}&rex-api-call=rexstan&action=1&target=${this.dataset.name}`)
                .then((response) => {
                    if (response.ok && 200 == response.status) {
                        return response.json();
                    }
                    console.error(response.text);
                    throw new Error(`${response.status} ${response.statusText}`);
                })
                .then((data) => {
                    // kein neuen Meldungen => Knoten löchen
                    if (0 == data.rc) {
                        if (confirm(this.dataset.name + '\n\nFür diese Datei wurden keine Fehler mehr gefunden.\nDer Block wird entfernt')) {
                            this.remove();
                        }
                        return;
                    }
                    // HTML-Content empfangen => content einfügen
                    this.__collapseNode.innerHTML = data.content;
                    Rexstan.event(this, Rexstan.evtCountTotal);
                    // neue Meldungen => Suche neu anfordern
                    if (2 == data.rc) {
                        Rexstan.event(this, `${Rexstan.evtSearch}.query`);
                    }
                })
                .catch((e) => {
                    alert(e.message);
                })
                .finally(() => {
                    this.__rexAjaxLoaderId = Rexstan.hideLoader(this.__rexAjaxLoaderId);
                });
        }
    }
);

/** <rexstan-message> Stellt den einzelnen Message-Eintrag dar.
 * 
 * Dieses CustomHTML reagiert selbständig auf die Eingaben im Suchfeld.
 * Das Suchfeld übermittelt die Eingabe mittels Event, der auf dem
 * übergeodeneten (__root) Node <rexstan-analysis> abgegriffen wird.
 * 
 *  - Fundstellen im Text werden farbig markiert.
 *    (this.__message.replace(event.detail.regex, Rexstan.htmlSearchHighlight);)
 *  - Der Eintrag erhält die Klasse .rexstan-search-hit
 * 
 */
customElements.define('rexstan-message',
    class extends Rexstan.BaseElement(HTMLElement) {

        __root = null
        __message = ''
        __haystack = ''

        connectedCallback() {
            this.__root = this.closest('rexstan-analysis');
            if (!this.__root) {
                console.error('Container "<rexstan-analysis>" not found; features disabled', this);
                return;
            }
            super.connectedCallback();
            this.addEventListener('rexstan:tip', this._toggleTip.bind(this));
            this.addEventListener('rexstan:clipboard', this._toClipboard.bind(this));
        }

        childrenAvailableCallback() {
            this.parsed = true;
            this.__container = this.querySelector('.rexstan-message-text');
            this.__message = this.__container.innerHTML;
            this.__haystack = this.__message.toLowerCase();
            this.__root.addEventListener(Rexstan.evtSearch, this._applySearch.bind(this));
        }

        disconnectedCallback() {
            this.__root.removeEventListener(Rexstan.evtSearch, this._applySearch.bind(this));
            this.removeEventListener('rexstan:tip', this._toggleTip.bind(this));
            this.removeEventListener('rexstan:clipboard', this._toClipboard.bind(this));
        }

        _applySearch(event) {
            if (event.detail.hasSearch) {
                if (Rexstan.searchKey4Tips == event.detail.search) {
                    this.__container.innerHTML = this.__message;
                    this.classList.toggle(Rexstan.flagSearchHit, null !== this.querySelector('.rexstan-tip'));
                } else if (this.__haystack.indexOf(event.detail.search) >= 0) {
                    this.__container.innerHTML = this.__message.replace(event.detail.regex, Rexstan.htmlSearchHighlight);
                    this.classList.add(Rexstan.flagSearchHit);
                } else {
                    this.__container.innerHTML = this.__message;
                    this.classList.remove(Rexstan.flagSearchHit);
                }
            } else {
                this.__container.innerHTML = this.__message;
                this.classList.remove(Rexstan.flagSearchHit);
            }
        }

        _toggleTip(event) {
            this.classList.toggle('rexstan-tip-closed');
        }
        _toClipboard(event) {
            navigator.clipboard.writeText(`\'${event.detail}\'`);
        }
    }
);

/** <rexstan-tabset>
 * 
 * Verwaltet die Tabs zu einem Tab-Menü:
 * - Boostrap 3.4 Tabset
 * - Das Menü-UL ist über das Attribut data-navigation angegeben (<ul id=..). Ohne Angabe wird
 *   der unmittelbar vor diesem Container stehende Block angenommen (was nict stimen muss)
 * - dieser Container entspricht dem <div class="tab-content"> und setzt die Klasse selbst
 * - Aus der Session wird die ID des aufzublendenden Tabs abgerufen
 *   Default: erster Tab
 * - bei jeder Änderung wird die ID des aktiven Tabs in der Session gespeichert
 * - Initial darf keiner der Tabs ( child-Nodes ) aktiviert sein! Sonst flackert es beim 
 *   Umschalten durch den Session-Abruf
 */
customElements.define('rexstan-tabset',
    class extends Rexstan.BaseElement(HTMLElement) {

        connectedCallback() {
            this.classList.add('tab-content');
            super.connectedCallback();
        }

        childrenAvailableCallback() {
            this.parsed = true;
            let activeTab = sessionStorage.getItem(Rexstan.sessionSettingsTabset);
            if (!activeTab) {
                activeTab = this.querySelector(':scope > div[id]');
                if (activeTab) {
                    activeTab = `#${activeTab.id}`;
                }
            }
            console.log(activeTab);
            if (activeTab) {
                let navigation = this.dataset.navigation;
                let target;
                if( navigation ) {
                    target = document.getElementById(navigation);
                }
                if( !target ) {
                    target = this.previousElementSibling;
                }
                if( target ) {
                    target = target.querySelector(`a[href="${activeTab}"]`);
                }
                $(target).tab('show');
            }
            $(this.previousElementSibling).on('shown.bs.tab', this._saveToSession.bind(this));
        }

        disconnectedCallback() {
            $(this.this.previousElementSibling).off('shown.bs.tab', this._saveToSession.bind(this));
        }

        _saveToSession(event) {
            sessionStorage.setItem(Rexstan.sessionSettingsTabset, event.target.getAttribute('href'));
        }
    }
);
