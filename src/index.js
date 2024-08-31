define(["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter"], function (dojo, declare) {
  let endTime = null;
  let flyTimer = null;
  let isMobile = false;
  let spotlightPlane = null;
  let suppressSounds = [];
  const uniqJsError = {};

  const airportMap = {
    ATL: _("Atlanta"),
    DEN: _("Denver"),
    DFW: _("Dallas"),
    JFK: _("New York"),
    LAX: _("Los Angeles"),
    MIA: _("Miami"),
    ORD: _("Chicago"),
    SEA: _("Seattle"),
    SFO: _("San Francisco"),
  };

  // Emoji
  const escapeRegExp = (txt) => txt.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&");
  const emojiMap = {
    ":)": "🙂",
    ":-)": "🙂",
    ";)": "😉",
    ";-)": "😉",
    ":$": "🤭",
    ":-$": "🤭",
    ":partying_face:": "🥳",
    "8)": "😎",
    "8-)": "😎",
    ":vip:": "🤩",
    ":D": "😀",
    ":-D": "😀",
    ":P": "🤪",
    ":-P": "🤪",
    ";P": "🤪",
    ";-P": "🤪",
    ":/": "🫤",
    ":-/": "🫤",
    ":(": "🙁",
    ":-(": "🙁",
    ";(": "😢",
    ";-(": "😢",
    ":'(": "😢",
    ":S": "😖",
    ":-S": "😖",
    ":@": "😡",
    ":-@": "😡",
    ">:@": "😡",
    ":O": "😮",
    ":-O": "😮",
    ":0": "😮",
    ":-0": "😮",
    O_O: "🤯",
    ":airplane:": "✈️",
  };
  const emojiUnique = Object.fromEntries(Object.values(emojiMap).map((value) => [value, value]));
  const emojiPattern = new RegExp("(^|\\s+)(" + Object.keys(emojiMap).map(escapeRegExp).join("|") + ")(?=$|\\s+)", "gi");

  // Local storage
  const getStorage = (key) => {
    try {
      return localStorage.getItem(`nowboarding.${key}`);
    } catch (e) {
      // Local storage unavailable
    }
  };
  const saveStorage = (key, value) => {
    try {
      localStorage.setItem(`nowboarding.${key}`, value);
      return true;
    } catch (e) {
      // Local storage unavailable
    }
    return false;
  };

  // Sounds
  const playSoundSuper = window.playSound;
  const getRandomInt = (min, max) => Math.floor(Math.random() * (max - min + 1) + min);

  // Viewport
  const viewportEl = document.querySelector('meta[name="viewport"]');
  const debounce = (callback, ctx, wait) => {
    let timeout;
    return (...args) => {
      window.clearTimeout(timeout);
      timeout = window.setTimeout(() => callback.apply(ctx, args), wait);
    };
  };

  return declare("bgagame.nowboarding", ebg.core.gamegui, {
    constructor() {
      dojo.place("loader_mask", "overall-content", "before");
      this.updateMobile();
      this.onGameUiWidthChange = debounce(
        () => {
          this.updateMobile();
          this.updateViewport();
          const logMode = !isMobile && document.body.clientWidth > 1640 ? document.getElementById("preference_global_control_logsSecondColumn")?.value : "0";
          this.switchLogModeTo(logMode);
          this.adaptChatbarDock();
          this.adaptStatusBar();
          this.resizeMap();
        },
        this,
        100
      );
    },

    setup(gamedatas) {
      console.log("🐣 Setup", gamedatas);

      // Setup flight plans (game over)
      if (gamedatas.plans) {
        this.renderFlightPlans();
      }
      document.getElementById("pageheader_gameview").addEventListener("click", (ev) => {
        window.setTimeout(() => this.resizeMap(), 0);
      });

      // Setup chat
      this.chatHeaderEl = document.getElementById("nbchatheader");
      this.chatHeaderEl.insertAdjacentText("beforeend", __("lang_mainsite", "Discuss at this table"));
      this.chatHeaderEl.insertAdjacentElement("afterend", document.getElementById("spectatorbox"));
      document.getElementById("nbchathide").addEventListener("click", (ev) => {
        // move table chat to BGA popup
        this.moveChatElements(false);
        document.getElementById("nbchat").style.display = "none";
        this.resizeMap();
        this.updateViewport(false);
      });
      if (gamedatas.hourTiming) {
        for (const hourTiming of gamedatas.hourTiming) {
          this.appendNbChatHourTiming(hourTiming);
        }
      }

      // Setup common
      const titleEl = document.getElementById("page-title");
      titleEl.insertAdjacentHTML("beforebegin", '<div id="nbprogress"><div id="nbfill"></div></div>');
      this.fillEl = document.getElementById("nbfill");
      this.scaleEl = document.getElementById("nbscale");
      this.renderCommon();
      this.renderCountdown();

      // Setup map
      this.mapEl = document.getElementById("nbmap");
      this.mapEl.classList.add(gamedatas.map.name);
      const manifestContainer = {
        SEA: "manifests-top",
        SFO: gamedatas.map.name == "map45" ? "manifests-bottom" : "manifests-top",
        DEN: "manifests-top",
        ORD: "manifests-top",
        JFK: "manifests-top",
        ATL: gamedatas.map.name == "map45" ? "manifests-right" : "manifests-bottom",
        LAX: "manifests-bottom",
        DFW: "manifests-bottom",
        MIA: "manifests-bottom",
      };
      if (gamedatas.map.name == "map45") {
        document.getElementById("manifests-bottom").insertAdjacentHTML("beforeend", `<div id="manifest-spacer"></div>`);
      }
      for (const node in gamedatas.map.nodes) {
        this.renderMapNode(node);
        if (node.length == 3) {
          this.renderMapManifest(node, manifestContainer[node]);
        }
      }
      this.resizeMap();
      this.renderWeather();

      // Setup planes
      if (gamedatas.planes) {
        for (const planeId in gamedatas.planes) {
          const plane = gamedatas.planes[planeId];
          this.renderPlane(plane);
          this.renderPlaneGauges(plane);
        }
      }

      // Setup pax
      if (gamedatas.pax) {
        for (const paxId in gamedatas.pax) {
          const pax = gamedatas.pax[paxId];
          this.renderPax(pax);
          if (pax.vipInfo?.key == "DOUBLE") {
            const double = { ...pax, id: pax.id * -1, cash: 0 };
            gamedatas.pax[double.id] = double;
            this.renderPax(double);
          }
        }
        this.renderMapCounts();
      }
      // Empty seats
      if (gamedatas.planes) {
        for (const planeId in gamedatas.planes) {
          const plane = gamedatas.planes[planeId];
          this.renderPlaneEmptySeats(plane);
        }
      }
      this.sortManifests();

      // Setup sounds
      window.playSound = this.playSound.bind(this);

      // Setup notifications
      dojo.subscribe("buildPrimary", this, "onNotify");
      dojo.subscribe("buys", this, "onNotify");
      dojo.subscribe("complaint", this, "onNotify");
      dojo.subscribe("flyTimer", this, "onNotify");
      dojo.subscribe("hour", this, "onNotify");
      dojo.subscribe("move", this, "onNotify");
      dojo.subscribe("pax", this, "onNotify");
      dojo.subscribe("plans", this, "onNotify");
      dojo.subscribe("planes", this, "onNotify");
      dojo.subscribe("sound", this, "onNotify");
      dojo.subscribe("vip", this, "onNotify");
      dojo.subscribe("weather", this, "onNotify");
      this.notifqueue.setSynchronous("complaint", 2000);
      this.notifqueue.setSynchronous("flyTimer", 5500);
      this.notifqueue.setSynchronous("weather", 2000);

      // Setup preferences
      this.onGameUserPreferenceChanged(150, this.getGameUserPreference(150));
    },

    // ----------------------------------------------------------------------

    /* @Override */
    format_string_recursive(log, args) {
      if (log && args) {
        // Translate log message
        log = this.clienttranslate_string(log) || "";

        // Create a new object to handle string substitution
        const sub = {};
        for (const k in args) {
          if (k == "i18n") {
            continue;
          }

          // Convert argument to (translated) string
          if (args[k]?.log && args[k]?.args) {
            // Process nested arguments recursively
            sub[k] = this.format_string_recursive(args[k].log, args[k].args);
          } else {
            sub[k] = args[k];
            if (args.i18n?.includes(k)) {
              sub[k] = this.clienttranslate_string(sub[k]);
            }
          }

          // Format argument with HTML
          if (k == "1" || k == "alliance") {
            sub[k] = `<span class="nbtag alliance alliance-${sub[k]}"><i class="icon logo logo-${sub[k]}"></i> ${sub[k]}</span>`;
          } else if (k == "cash") {
            sub[k] = `<span class="nbtag cash"><i class="icon cash"></i> ${sub[k]}</span>`;
          } else if (k == "complaint") {
            sub[k] = `<span class="nbtag complaint"><i class="icon complaint"></i> ${sub[k]}</span>`;
          } else if (k == "countToWin" || k == "location") {
            sub[k] = `<b>${sub[k]}</b>`;
          } else if (k == "seat") {
            sub[k] = `<span class="nbtag seat"><i class="icon seat"></i> ${sub[k]}</span>`;
          } else if (k == "speed") {
            sub[k] = `<span class="nbtag speed"><i class="icon speed"></i> ${sub[k]}</span>`;
          } else if (k == "temp") {
            sub[k] = `<span class="nbtag ${args.tempIcon}"><i class="icon ${args.tempIcon}"></i> ${sub[k]}</span>`;
          } else if (k == "vip") {
            sub[k] = `<span class="nbtag vip"><i class="icon vipstar"></i> ${sub[k]}</span>`;
          } else if (k == "wrapper") {
            if (sub[k] == "weatherFlex") {
              log = `<div class="log-flex-wrapper"><div class="weather node"><i class="icon weather-${args.weatherIcon}"></i></div><div>${log}</div></div>`;
            }
          }
        }

        // Finally apply string substitution
        try {
          return dojo.string.substitute(log, sub);
        } catch (e) {
          console.error("string.substitute error", e);
          return log;
        }
      }
      return "";
    },

    /* @Override */
    showMessage(msg, type) {
      if (type == "error" && msg?.startsWith("!!!")) {
        return; // suppress red banner and gamelog message
      }
      this.inherited(arguments);
    },

    /* @Override */
    onZoomToggle() {
      // do nothing
    },

    /*@Override */
    createChatBarWindow(args) {
      const output = this.inherited(arguments);
      const chat = this.chatbarWindows[`table_${this.table_id}`];
      if (args.type == "table" && args.id == this.table_id && chat && !chat.nbinit) {
        console.log("🐣 Setup chat");
        chat.nbinit = true;
        chat.input.readaptChatHeight = () => {};
        this.autoChatWhilePressingKey = new dijit.TooltipDialog({ id: "autoChatWhilePressingKey", content: "" });
        this.moveChatElements(true);
        document.getElementById(`chatwindowpreview_table_${this.table_id}`).style.display = "none";
      }
      return output;
    },

    moveChatElements(inline) {
      const barEl = document.getElementById(`chatbarbelowinput_table_${this.table_id}`);
      const inputEl = document.getElementById(`chatbarinput_table_${this.table_id}_input`);
      if (inline) {
        // move chat elements to inline
        this.chatHeaderEl.insertAdjacentElement("afterend", barEl);
        this.chatHeaderEl.insertAdjacentElement("afterend", inputEl);
      } else {
        // move chat elements to BGA popup
        const bgaEl = document.getElementById(`chatbarinput_table_${this.table_id}`);
        bgaEl.querySelector(".chatinputctrl").insertAdjacentElement("afterbegin", inputEl);
        bgaEl.insertAdjacentElement("afterend", barEl);
      }
    },

    /* @Override */
    expandChatWindow(id, autoExpand) {
      const isTable = id == `table_${this.table_id}`;
      if (isTable && autoExpand) {
        // don't auto-expand, instead just focus the text box
        document.getElementById(`chatbarinput_table_${this.table_id}_input`).focus();
        return;
      }
      this.inherited(arguments);
      if (isTable) {
        // move table chat to BGA popup
        this.moveChatElements(false);
        document.getElementById("nbchat").style.display = "none";
        this.resizeMap();
      }
      this.updateViewport(true);
    },

    /* @Override */
    collapseChatWindow(id) {
      this.inherited(arguments);
      if (id == `table_${this.table_id}`) {
        // move table chat to inline
        this.moveChatElements(true);
        document.getElementById("nbchat").style.display = "";
        document.getElementById(`chatwindowpreview_table_${this.table_id}`).style.display = "none";
        this.resizeMap();
      }
      this.updateViewport(false);
    },

    /* @Override */
    onShowPredefined(t) {
      this.inherited(arguments);
      const predefinedEl = document.getElementById(`chatbarinput_predefined_table_${this.table_id}_dropdown`);
      if (predefinedEl) {
        predefinedEl.style.zIndex = 99999;
      }
    },

    updateMobile() {
      isMobile = document.body.clientWidth < 1180;
      document.body.classList.toggle("desktop_version", !isMobile);
      document.body.classList.toggle("mobile_version", isMobile);
      const scale = Math.min(1.5, Math.max(1, document.body.clientWidth / window.screen.width));
      document.body.style.setProperty("--mobile-scale", scale);
      console.log("📱 isMobile", isMobile, "clientWidth", document.body.clientWidth, "screenWidth", window.screen.width, "scale", scale);
    },

    updateViewport(chatVisible) {
      if (chatVisible === undefined) {
        chatVisible = false;
        for (const w in this.chatbarWindows) {
          if (this.chatbarWindows[w].status == "expanded") {
            chatVisible = true;
            break;
          }
        }
      }
      // Force device-width during chat
      viewportEl.content = `width=${chatVisible ? "device-width" : "850"},interactive-widget=resizes-content`;
    },

    /* @Override */
    onPlaceLogOnChannel(notif) {
      const chatId = this.next_log_id;
      const result = this.inherited(arguments);
      if (result && notif.channelorig == `/table/t${this.table_id}` && (notif.type == "chatmessage" || notif.type == "tablechat" || (notif.type == "startWriting" && notif.args.player_id != this.player_id))) {
        const plane = this.gamedatas.planes[notif.args.player_id];
        const alliance = plane?.alliances?.length ? plane.alliances[0] : null;
        if (notif.type == "startWriting") {
          // Add writing indicator to inline chat
          const writingEl = document.getElementById(`nbwriting_${notif.args.player_id}`);
          if (!writingEl) {
            this.appendNbChatMessage({
              alliance,
              playerId: notif.args.player_id,
              playerName: notif.args.player_name,
              time: Date.now(),
              writing: notif.args.player_name,
            });
          }
        } else if (chatId != this.next_log_id) {
          if (alliance) {
            // Color BGA chat
            document.getElementById(`dockedlog_${chatId}`)?.querySelector(".roundedboxinner").classList.add(`alliance-${alliance}`);
          }

          // Add message to inline chat
          let message = "";
          if (notif.log == "${player_name} ${message}") {
            message = this.addSmileyToText(notif.args.message || "");
          } else if (notif.log == "${player_name} ${text}") {
            message = this.addSmileyToText(notif.args.text || "");
          } else {
            message = this.format_string_recursive(notif.log, notif.args);
          }
          let tempEl = document.createElement("div");
          tempEl.innerHTML = message;
          const messagePlain = tempEl.textContent;
          tempEl.innerHTML = notif.args.player_name;
          const playerName = tempEl.textContent;
          this.appendNbChatMessage({
            alliance,
            message,
            messagePlain,
            playerId: notif.args.player_id,
            playerName,
            time: notif.time * 1000,
          });
        } else {
          console.log("Chat ignored by next_log_id", JSON.stringify(notif, undefined, 2));
        }
      }
      return result;
    },

    appendNbChatMessage(args) {
      const self = args.playerId == this.player_id ? "self" : "";
      if (args.writing) {
        args.message = '<i class="icon typing"></i>';
      }
      if (!self && !args.alliance) {
        args.message = `<b>${args.playerName}</b>: ${args.message}`;
      }
      let avatarHtml = "";
      if (!self) {
        const avatarUrl = document.getElementById(`avatar_${args.playerId}`)?.src || "https://x.boardgamearena.net/data/avatar/default_32.jpg";
        avatarHtml = `<a target="_blank" href="/player?playerId=${args.playerId}" title="${args.playerName}"><img class="avatar emblem" src="${avatarUrl}"></a>`;
      }
      let translateHtml = "";
      if (args.messagePlain) {
        let lang = dojoConfig.locale;
        if (lang == "zh") {
          lang = "zh-TW";
        } else if (lang == "zh-cn") {
          lang = "zh-CN";
        } else if (lang == "he") {
          lang = "iw";
        }
        translateHtml = ` &mdash; <a class="nbtranslate" target="_blank" href="https://translate.google.com/?sl=auto&tl=${lang}&text=${encodeURIComponent(args.messagePlain)}&op=translate"><i class="icon translate"></i> ${_("Translate")}</a>`;
      }
      const time = new Date(args.time).toLocaleString([], { month: "2-digit", day: "2-digit", hour: "2-digit", minute: "2-digit", hour12: false });
      const order = Math.floor(args.time / 1000) - 1701388800; // 2023-12-01
      const logHtml = `<div class="nbchatlog">
  <div class="nbchatmsg alliance-${args.alliance}">${args.message}</div>
  <div class="nbchattime">${time}${translateHtml}</div>
</div>`;

      let wrapHtml = "";
      if (args.writing) {
        wrapHtml = `<div id="nbwriting_${args.playerId}" data-player-name="${args.writing}" class="nbwriting nbchatwrap ${self}" data-order="${order}">${avatarHtml}${logHtml}</div>`;
      } else {
        wrapHtml = `<div class="nbchatwrap ${self}" data-order="${order}">${avatarHtml}${logHtml}</div>`;
      }
      this.appendNbChat(wrapHtml, order, !args.writing);
    },

    appendNbChatHourTiming(args) {
      let msg = _(args.hourDesc);
      if (args.round) {
        msg += ` (${args.round}/${args.total})`;
      }
      const order = Math.floor(args.time) - 1701388800; // 2023-12-01
      let html = `<div class="nbchatwrap hour" data-order="${order}">&mdash; ${msg} &mdash;</div>`;
      this.appendNbChat(html, order, true);
    },

    appendNbChat(html, order, scroll) {
      const scrollEl = document.getElementById("nbchatscroll");
      let inserted = false;
      for (const child of scrollEl.children) {
        const childOrder = +child.dataset.order;
        if (order >= childOrder) {
          child.insertAdjacentHTML("beforebegin", html);
          inserted = true;
          break;
        }
      }
      if (!inserted) {
        scrollEl.insertAdjacentHTML("beforeend", html);
      }
      if (scroll) {
        scrollEl.scrollTop = scrollEl.scrollHeight * -1;
      }
    },

    /* @Override */
    onUpdateIsWritingStatus(chatWindow) {
      if (chatWindow == `table_${this.table_id}`) {
        const writingEls = document.querySelectorAll("#nbchatscroll .nbwriting");
        for (let writingEl of writingEls) {
          if (!this.chatbarWindows[chatWindow].is_writing_now[writingEl.dataset.playerName]) {
            writingEl.remove();
          }
        }
      }
      this.inherited(arguments);
    },

    /* @Override */
    addSmileyToText(txt) {
      try {
        txt = txt.replaceAll(emojiPattern, (match) => " " + emojiMap[match.trim().toUpperCase()] + " ");
        txt = txt.replaceAll("&zwj;", "\u200D").replaceAll(/(\p{RGI_Emoji}+)/gv, '<span class="emoji">$1</span>');
      } catch (e) {
        // unicodeSets flag not supported
      }
      return txt;
    },

    /* @Override */
    getSmileyClassToCodeTable() {
      return emojiUnique;
    },

    /* @Override */
    adaptPlayersPanels() {
      // do nothing
    },

    /* @Override */
    setModeInstataneous() {
      document.getElementById("leftright_page_wrapper").style.display = "none";
      this.inherited(arguments);
    },

    /* @Override */
    unsetModeInstantaneous() {
      document.getElementById("leftright_page_wrapper").style.display = "block";
      this.inherited(arguments);
    },

    /* @Override */
    updateReflexionTime() {
      this.inherited(arguments);
      if (this.gamedatas.gamestate.name == "fly" && endTime) {
        const seconds = (endTime - Date.now()) / 1000;
        if (!this.gamedatas.gamestate.args.sound && seconds <= 8) {
          // Play the clock sound only once, at 8 seconds
          playSoundSuper("time_alarm");
          this.gamedatas.gamestate.args.sound = true;
        }
        this.renderCountdown(this.formatReflexionTime(seconds).string);
      }
    },

    playSound(sound) {
      if (this.instantaneousMode) {
        console.warn("🔈 Suppress sound (instantaneousMode)", sound);
        return;
      }
      if (this.gamedatas.gamestate.name == "fly" && endTime && sound == "time_alarm") {
        console.warn("🔈 Suppress sound", sound);
        return;
      }
      const index = suppressSounds.indexOf(sound);
      if (index > -1) {
        suppressSounds.splice(index, 1);
        console.warn("🔈 Suppress sound", sound);
        return;
      }
      playSoundSuper(sound);
    },

    /* @Override */
    onScriptError(msg) {
      const bgaKnownError = msg.includes("During notification resultsAvailable") && msg.includes("switchToGameResults");
      if (!bgaKnownError && !uniqJsError[msg]) {
        uniqJsError[msg] = true;
        console.error("⛔ Reporting JavaScript error", msg);
        this.takeAction("jsError", { msg, userAgent: navigator.userAgent });
      }
      this.inherited(arguments);
    },

    // ----------------------------------------------------------------------

    isReadOnly() {
      return this.isSpectator || typeof g_replayFrom != "undefined" || g_archive_mode;
    },

    onEnteringState(stateName, args) {
      if (args.args?.titleMessage) {
        this.renderTitleMessage(args.args.titleMessage);
      }
      console.log("onEnteringState", stateName, args);
      if (args.updateGameProgression) {
        this.fillEl.style.width = args.updateGameProgression + "%";
      }

      if (stateName == "fly") {
        if (args.args.remain != null) {
          document.body.classList.add("no_time_limit");
          if (!this.isReadOnly()) {
            // Start timer
            if (flyTimer) {
              window.clearTimeout(flyTimer);
            }
            const millis = Math.max(0, args.args.remain) * 1000;
            const timerMillis = millis + Math.random() * 1500;
            console.log("⌚ Timer start", timerMillis);
            flyTimer = window.setTimeout(() => this.takeAction("flyTimer", { lock: false }), timerMillis);
            endTime = Date.now() + millis;
          }
        }
      } else if (stateName == "maintenance") {
        // Stop timer
        if (flyTimer) {
          window.clearTimeout(flyTimer);
          flyTimer = null;
          endTime = null;
        }
        if (!this.gamedatas.noTimeLimit) {
          document.body.classList.remove("no_time_limit");
        }
        this.renderCountdown();
        this.renderTitleMessage();
      } else if (stateName == "prepare" || stateName == "gameEnd") {
        this.stabilizerOff();
      }
    },

    onUpdateActionButtons(stateName, args) {
      console.log(`▶️ State ${stateName}`, args);
      document.getElementById("nbwrap")?.remove();
      if (!this.isSpectator) {
        // Inactive players can undo or go back
        if (stateName == "build" || stateName == "buildAlliance2" || stateName == "buildUpgrade" || stateName == "prepare") {
          this.addActionButton("button_undo", _("Start Over"), () => this.takeAction("undo"), null, false, "gray");
        }
        if (stateName == "fly") {
          this.addActionButton("button_flyAgain", _("Go Back"), () => this.takeAction("flyAgain"), null, false, "gray");
        }

        // Inactive players clear moves
        if (stateName == "fly" || stateName == "maintenance") {
          this.deleteMoves();
        }

        // Active players
        if (this.isCurrentPlayerActive()) {
          if (stateName == "buildAlliance" || stateName == "buildAlliance2" || stateName == "buildUpgrade") {
            this.buys = args.buys;
            this.renderBuys();
          } else if (stateName == "prepareBuy") {
            this.addActionButton("button_prepareDone", _("Ready"), () => {
              let dialog = null;
              if (this.gamedatas.vip && this.gamedatas.hour.vipNeed && !this.gamedatas.hour.vipNew) {
                const roundRemain = this.gamedatas.hour.total - this.gamedatas.hour.round + 1;
                dialog = this.format_string_recursive(_("You did not accept a VIP, but ${vipRemain} VIPs remain and ${hourDesc} ends in ${roundRemain} rounds"), {
                  hourDesc: this.gamedatas.hour.hourDesc,
                  roundRemain,
                  vipRemain: this.gamedatas.hour.vipRemain,
                });
              }
              const dialogPromise = dialog ? this.confirmationDialogPromise(dialog) : Promise.resolve();
              dialogPromise.then(
                () => this.takeAction("prepareDone"),
                () => {}
              );
            });
            if (this.gamedatas.hour.vipRemain) {
              const acceptTxt = this.format_string_recursive(_("Accept VIP (${vipRemain} remaining)"), { vipRemain: this.gamedatas.hour.vipRemain });
              this.addActionButton("button_vipAccept", acceptTxt, () => this.takeAction("vip", { accept: true }), null, false, this.gamedatas.hour.vipNeed ? "red" : "blue");
              this.addActionButton("button_vipDecline", _("Decline VIP"), () => this.takeAction("vip", { accept: false }));
              const disabled = this.gamedatas.hour.vipNew ? "button_vipAccept" : "button_vipDecline";
              document.getElementById(disabled).classList.add("disabled");
            }
            if (args.ledger?.length > 0) {
              this.addActionButton("button_undo", _("Start Over"), () => this.takeAction("undo"), null, false, "gray");
            }
            this.buys = args.buys;
            this.renderBuys();
            this.renderLedger(args);
          } else if (stateName == "preparePay") {
            this.addActionButton("button_pay", _("Pay"), () => {
              const paid = [];
              document.querySelectorAll("#nbbuys .paybutton:not(.ghostbutton)").forEach((el) => paid.push(el.dataset.cash));
              this.takeAction("pay", { paid });
            });
            this.addActionButton("button_buyAgain", _("Go Back"), () => this.takeAction("buyAgain"), null, false, "gray");
            this.renderWalletPay(args.wallet, args.suggestion);
          } else if (stateName == "flyPrivate") {
            if (!this.bRealtime) {
              this.addActionButton("button_flyDoneSnooze", _("Snooze Until Next Move"), () => this.takeAction("flyDone", { snooze: true }).then(() => this.stabilizerOff()), null, false, "gray");
            }
            if (args.speedRemain > 0) {
              const txt = this.format_string_recursive(_("End Round Early (${speedRemain} speed remaining)"), { speedRemain: args.speedRemain });
              this.addActionButton("button_flyDone", txt, () => this.takeAction("flyDone").then(() => this.stabilizerOff()), null, false, "red");
            } else {
              this.addActionButton("button_flyDone", _("End Round"), () => this.takeAction("flyDone").then(() => this.stabilizerOff()), null, false, "blue");
            }
            // Update the possible moves
            this.deleteMoves();
            if (args?.moves) {
              this.renderMoves(args.moves);
            }
          }
        }
      }
    },

    onNotify(notif) {
      console.log(`💬 Notify ${notif.type}`, notif.args);
      if (notif.type == "buildPrimary") {
        this.gamedatas.players[notif.args.player_id].color = notif.args.color;
        this.gamedatas.planes[notif.args.plane.id] = notif.args.plane;
        this.renderPlane(notif.args.plane);
        this.renderPlaneGauges(notif.args.plane);
      } else if (notif.type == "buys") {
        if (this.buys && document.getElementById("nbbuys") != null) {
          for (const newBuy of notif.args.buys) {
            for (const buy of this.buys) {
              if (buy.type == newBuy.type && buy.alliance == newBuy.alliance) {
                buy.ownerId = newBuy.ownerId;
                break;
              }
            }
          }
          this.renderBuys();
        }
      } else if (notif.type == "complaint") {
        suppressSounds = ["yourturn"];
        playSound("nowboarding_complaint" + getRandomInt(1, 4));
        this.gamedatas.complaint = notif.args.total;
        this.gamedatas.countToWin = notif.args.countToWin;
        this.renderCommon();
      } else if (notif.type == "flyTimer") {
        suppressSounds = ["yourturn"];
        playSound("nowboarding_chime");
        if (flyTimer) {
          window.clearTimeout(flyTimer);
          flyTimer = null;
          endTime = null;
        }
        this.showMessage(_("Time is up!") + '<div class="flyTimer"><i class="icon timer"></i></div>', "info");
        this.deleteMoves();
      } else if (notif.type == "hour") {
        this.gamedatas.hour = notif.args;
        if (this.gamedatas.hour.hour == "FINALE") {
          suppressSounds = ["yourturn"];
          playSound("nowboarding_walkway");
        }
        if ((this.gamedatas.hour.vipNew || this.gamedatas.hour.vipRemain) && this.gamedatas.gamestate.private_state?.name == "prepareBuy") {
          document.getElementById("button_vipAccept")?.classList.toggle("disabled", this.gamedatas.hour.vipNew);
          document.getElementById("button_vipDecline")?.classList.toggle("disabled", !this.gamedatas.hour.vipNew);
        }
        if (this.gamedatas.hour.hourTiming) {
          this.appendNbChatHourTiming(this.gamedatas.hour.hourTiming);
        }
        this.renderCommon();
      } else if (notif.type == "move") {
        this.gamedatas.planes[notif.args.plane.id] = notif.args.plane;
        if (notif.args.player_id == this.player_id) {
          // Erase possible moves
          this.deleteMoves();
        }
        this.movePlane(notif.args.plane);
        this.renderPlaneGauges(notif.args.plane);
      } else if (notif.type == "pax") {
        let sound = false;
        for (const pax of notif.args.pax) {
          this.gamedatas.pax[pax.id] = pax;
          if (pax.playerId == this.player_id && pax.status == "CASH") {
            sound = true;
          }
          this.renderPax(pax);
          if (pax.vipInfo?.key == "DOUBLE") {
            const double = { ...pax, id: pax.id * -1, cash: 0 };
            this.gamedatas.pax[double.id] = double;
            this.renderPax(double);
          }
        }
        if (notif.args.countToWin != null) {
          this.gamedatas.countToWin = notif.args.countToWin;
          this.renderCommon();
        }
        if (sound) {
          playSound("nowboarding_cash");
        }
        this.renderMapCounts();
      } else if (notif.type == "plans") {
        this.gamedatas.plans = notif.args.plans;
        this.renderFlightPlans();
      } else if (notif.type == "planes") {
        for (const plane of notif.args.planes) {
          this.gamedatas.planes[plane.id] = plane;
          this.renderPlane(plane);
          this.renderPlaneGauges(plane);
          this.renderPlaneEmptySeats(plane);
        }
      } else if (notif.type == "sound") {
        suppressSounds = notif.args.suppress;
        playSound(`nowboarding_${notif.args.sound}`);
      } else if (notif.type == "vip") {
        this.gamedatas.vip = notif.args.overall;
        const vipEl = document.getElementById("nbcommon-vip");
        if (vipEl) {
          vipEl.textContent = this.gamedatas.vip;
        }
      } else if (notif.type == "weather") {
        suppressSounds = ["yourturn"];
        playSound("nowboarding_plane");
        this.gamedatas.map.weather = notif.args.weather;
        this.renderWeather();
      }
    },

    onGameUserPreferenceChanged(id, value) {
      console.log("Preference changed", id, value);
      if (id == 150) {
        document.body.classList.toggle("no-animation", value == 2);
        document.body.classList.toggle("no-pinging", value > 0);
      }
    },

    // Utilities
    // ----------------------------------------------------------------------

    confirmationDialogPromise(message) {
      return new Promise((resolve, reject) => {
        this.confirmationDialog(
          message,
          () => resolve(),
          () => reject()
        );
      });
    },

    takeAction(action, data) {
      return new Promise((resolve, reject) => {
        // Prepare data
        data = data || {};
        data.version = this.gamedatas.version;
        if (data.lock === false) {
          delete data.lock;
        } else {
          data.lock = true;
        }
        for (const key in data) {
          if (Array.isArray(data[key])) {
            data[key] = data[key].join(",");
          }
        }

        // Web call
        const method = action == "jsError" ? "post" : undefined;
        const start = Date.now();
        console.log(`👆 Take action ${action}`, data);
        this.ajaxcall(
          "/nowboarding/nowboarding/" + action + ".html",
          data,
          this,
          () => {},
          (error, errorMsg) => {
            const duration = Date.now() - start;
            if (error) {
              console.error(`Take action ${action} error in ${duration}ms`, errorMsg);
              if (errorMsg == "!!!checkVersion") {
                console.warn(`🆙 New version available`);
                this.infoDialog(
                  _("A new version of this game is now available"),
                  _("Reload Required"),
                  () => {
                    window.location.reload();
                  },
                  true
                );
              } else {
                reject(errorMsg);
              }
            } else {
              console.log(`Take action ${action} done in ${duration}ms`);
              resolve();
            }
          },
          method
        );
      });
    },

    takePaxAction(paxId) {
      if (this.isCurrentPlayerActive()) {
        const pax = this.gamedatas.pax[paxId];
        if (pax.status == "SEAT" && pax.playerId == this.player_id) {
          if (this.checkAction("deplane")) {
            this.takeAction("deplane", { paxId: Math.abs(pax.id), paxPlayerId: pax.playerId });
          }
        } else if (this.checkAction("board")) {
          this.takeAction("board", { paxId: Math.abs(pax.id), paxPlayerId: pax.playerId });
        }
      }
    },

    takeMoveAction(plane, move) {
      let dialog = null;
      if (plane.location.length == 3) {
        const pax = Object.values(this.gamedatas.pax);
        if (
          this.gamedatas.hour.hour == "MORNING" &&
          this.gamedatas.hour.round == 1 && // during the first round
          pax.filter((x) => x.playerId == plane.id && x.status == "SEAT").length == 0 && // plane is empty
          pax.filter((x) => x.location == plane.location && x.status == "PORT").length > 0 // airport is not
        ) {
          // Forgot to board
          dialog = this.format_string_recursive(_("You are leaving ${location} without boarding passengers") + "<br><br>" + _("Before moving, click passengers waiting at the airport to board them. This reminder only displays during the first round."), {
            location: plane.location,
          });
        } else if (pax.filter((x) => x.playerId == plane.id && x.status == "SEAT" && x.destination == plane.location && x.vipInfo?.key != "STORM" && x.vipInfo?.key != "WIND").length > 0) {
          // Forgot to deliver
          dialog = this.format_string_recursive(_("You are leaving ${location} without delivering passengers"), {
            location: plane.location,
          });
        }
      }
      const dialogPromise = dialog ? this.confirmationDialogPromise(dialog) : Promise.resolve();
      dialogPromise.then(
        () => {
          if (this.checkAction("move")) {
            this.takeAction("move", { from: plane.location, to: move.location });
          }
        },
        () => {}
      );
    },

    getElement(elementOrId) {
      if (typeof elementOrId == "string") {
        elementOrId = document.getElementById(elementOrId);
      }
      return elementOrId;
    },

    swapClass(el, toRemove, toAdd) {
      el = this.getElement(el);
      if (toRemove) {
        if (typeof toRemove == "string") {
          toRemove = [toRemove];
        }
        const classList = [...el.classList];
        classList.forEach((className) => {
          for (const c of toRemove) {
            if (className.startsWith(c)) {
              el.classList.remove(className);
            }
          }
        });
      }
      if (toAdd) {
        el.classList.add(toAdd);
      }
    },

    getRotationPlane(plane) {
      if (!plane.location || !plane.origin) {
        return null;
      }
      return this.getRotation(`node-${plane.location}`, `node-${plane.origin}`);
    },

    getRotation(el, otherEl) {
      const elPos = this.getElement(el)?.getBoundingClientRect();
      const otherPos = this.getElement(otherEl)?.getBoundingClientRect();
      if (!elPos || !otherPos) {
        return null;
      }
      let rotation = (Math.atan2(elPos.y - otherPos.y, elPos.x - otherPos.x) * 180) / Math.PI + 90;
      // Normalize the rotation to between -180 +180
      if (rotation < -180) {
        rotation += 360;
      } else if (rotation > 180) {
        rotation -= 360;
      }
      return rotation;
    },

    positionElement(el, start) {
      el.style.position = "absolute";
      el.style.top = `${start.top + window.scrollY}px`;
      el.style.left = `${start.left + window.scrollX}px`;
    },

    transitionElement(el, startFn) {
      return new Promise((resolve) => {
        const fallback = window.setTimeout(resolve, 1100);
        el.addEventListener("transitionend", () => {
          resolve();
          window.clearTimeout(fallback);
        });
        el.offsetHeight; // repaint
        startFn(el);
      });
    },

    translateElement(el, start, end) {
      return this.transitionElement(el, (el) => (el.style.transform = `translate(${end.x - start.x}px, ${end.y - start.y}px)`));
    },

    stabilizerOff() {
      console.log("🔓 Stabilizer off");
      // Remove gaps in map manifest
      document.querySelectorAll(".paxlist.is-map .paxslot.is-empty").forEach((slotEl) => slotEl.remove());
      this.sortManifests();
    },

    sortManifests() {
      const paxOrder = [];
      Object.values(this.gamedatas.pax).forEach(function (pax) {
        const paxEl = document.getElementById(`pax-${pax.id}`);
        if (paxEl) {
          // Sort by secret, vip DESC, anger DESC, destination, ID, cash DESC
          const order = (pax.status == "SECRET" ? 0 : 1) + "." + (pax.vipInfo ? 0 : 1) + "." + (5 - pax.anger) + "." + pax.destination + "." + String(Math.abs(pax.id)).padStart(2, "0") + "." + (5 - pax.cash);
          paxOrder.push({
            el: paxEl,
            order: order,
          });
        }
      });
      paxOrder.sort((a, b) => (a.order > b.order ? 1 : -1));
      paxOrder.forEach((o) => o.el.parentElement.parentElement.appendChild(o.el.parentElement));

      // Empty seats last
      document.querySelectorAll(".paxslot.is-empty").forEach((slotEl) => slotEl.parentElement.appendChild(slotEl));
    },

    // Rendering
    // ----------------------------------------------------------------------

    renderCommon() {
      let commonEl = document.getElementById("nbcommon");
      if (!commonEl) {
        const vipHtml = this.gamedatas.vip
          ? `<div class="nbsection">
        <div class="nblabel">${_("VIPs Remain")}</div>
        <div class="nbtag"><i class="icon vipstar"></i> <span id="nbcommon-vip">${this.gamedatas.vip}</span></div>
      </div>`
          : "";
        const commonHtml = `<div id="nbcommon">
  <div class="nbsection">
    <div class="nblabel">${_("Round")}</div>
    <div class="nbtag hour"><i class="icon"></i> <span></span></div>
  </div>
  <div class="nbsection">
    <div class="nblabel">${_("Map Size")}</div>
    <div class="nbtag"><input type="range" id="nbrange" min="40" max="100" step="2" value="60"></div>
  </div>
  <div class="nbsection">
    <div class="nblabel">${_("Complaints")}</div>
    <div class="nbtag"><i class="icon complaint"></i> <span id="nbcommon-complaint"></span></div>
  </div>
  <div class="nbsection">
    <div class="nblabel">${_("To Win")}</div>
    <div class="nbtag"><i class="icon towin"></i> <span id="nbcommon-towin"></span></div>
  </div>
  ${vipHtml}
</div>`;
        const parentEl = document.getElementById("player_boards");
        parentEl.insertAdjacentHTML("beforebegin", commonHtml);
        commonEl = document.getElementById("nbcommon");

        const nbrange = document.getElementById("nbrange");
        const adjustScale = (ev, save) => {
          this.scaleEl.style.width = `${nbrange.value}%`;
          this.resizeMap();
          if (save !== false) {
            saveStorage("scale", nbrange.value);
          }
        };
        nbrange.addEventListener("input", adjustScale);
        const initialScale = getStorage("scale") || 60;
        nbrange.value = initialScale;
        adjustScale(null, false);
      }

      const complaintTextEl = document.getElementById("nbcommon-complaint");
      complaintTextEl.innerHTML = `<span class="${this.gamedatas.complaint > 0 ? "bb" : ""}">${this.gamedatas.complaint}</span>/3`;
      const winTextEl = document.getElementById("nbcommon-towin");
      winTextEl.textContent = this.gamedatas.countToWin;
      const hourEl = commonEl.querySelector(".hour");
      const hourIconEl = hourEl.querySelector(".icon");
      const hourTextEl = hourEl.querySelector("span");
      this.swapClass(hourIconEl, "hour-", `hour-${this.gamedatas.hour.hour}`);
      let hourTxt = _(this.gamedatas.hour.hourDesc);
      if (this.gamedatas.hour.round) {
        hourTxt += ` (${this.gamedatas.hour.round}/${this.gamedatas.hour.total})`;
      }
      hourTextEl.textContent = hourTxt;
    },

    renderCountdown(txt) {
      let countdownEl = document.getElementById("nbcountdown");
      if (!countdownEl) {
        const titleEl = document.getElementById("page-title");
        titleEl.insertAdjacentHTML("afterbegin", `<div id="nbcountdown" title="${_("Flight Phase Timer")}"><i class="icon timer-off"></i></div>`);
        countdownEl = document.getElementById("nbcountdown");
      }
      if (txt) {
        countdownEl.classList.add("active");
        countdownEl.textContent = txt;
      } else if (this.gamedatas.timer) {
        countdownEl.classList.remove("active");
        countdownEl.textContent = this.formatReflexionTime(this.gamedatas.timer).string;
      }
    },

    async renderWeather() {
      const deletes = [];
      document.querySelectorAll("#nbmap .weather").forEach((el) => {
        deletes.push({
          el: el,
          promise: this.transitionElement(el, (el) => (el.style.opacity = "0")),
        });
      });
      if (deletes.length) {
        // Wait for delete animation
        console.log(`❌ Delete weather`, deletes.length);
        for (const d of deletes) {
          await d.promise;
          d.el.remove();
        }
      }

      // Add new weather
      for (const location in this.gamedatas.map.weather) {
        const token = this.gamedatas.map.weather[location];
        this.createWeather(location, token);
        if (token == "SLOW" && !location.endsWith("w")) {
          const locationW = location + "w";
          if (!this.gamedatas.map.nodes[locationW]) {
            this.gamedatas.map.nodes[locationW] = this.gamedatas.map.nodes[location];
            this.renderMapNode(locationW);
          }
          this.createWeather(locationW, token);
        }
      }
    },

    createWeather(location, token) {
      console.log(`🌤️ Create weather ${token} at ${location}`);
      const txt = token == "FAST" ? _("Tailwind: No fuel cost to move through this space") : _("Storm");
      const alliance = this.gamedatas.map.nodes[location];
      let specialHtml = "";
      if (alliance) {
        const specialTxt = this.format_string_recursive(_("Special Route: Restricted to alliance ${specialRoute}"), { specialRoute: alliance });
        specialHtml = `<div class="specialtag alliance-${alliance}" title="${specialTxt}"><i class="icon logo logo-${alliance}"></i></div>`;
      }
      this.mapEl.insertAdjacentHTML("beforeend", `<div id="weather-${location}" class="weather node node-${location}" title="${txt}" style="opacity: 0"><i class="icon weather-${token}"></i>${specialHtml}</div>`);
      const el = document.getElementById(`weather-${location}`);
      this.transitionElement(el, (el) => (el.style.opacity = null));
    },

    renderPlane(plane) {
      let planeEl = document.getElementById(`plane-${plane.id}`);
      if (plane.location && !planeEl) {
        // Create plane
        console.log(`✈️ Create plane ${plane.id} at ${plane.location}`);
        const cssClass = plane.id == this.player_id ? "mine" : "";
        const speedRemain = plane.speedRemain > 0 ? plane.speedRemain : "";
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="plane-${plane.id}" class="plane node node-${plane.location} ${cssClass}" title="${this.gamedatas.players[plane.id].name}"><div id="planeicon-${plane.id}" class="icon plane alliance-${plane.alliances[0]}"></div><div id="planespeed-${plane.id}" class="planespeed planespeed-${plane.alliances[0]}">${speedRemain}</div></div>`);
        const iconEl = document.getElementById(`planeicon-${plane.id}`);
        iconEl.style.background = this.getGradient(plane.alliances, "plane");
        const rotation = this.getRotationPlane(plane);
        if (rotation) {
          iconEl.style.transform = `rotate(${rotation}deg)`;
        }
        // Update panel
        const panelEl = document.getElementById(`overall_player_board_${plane.id}`);
        this.swapClass(panelEl, "panel-", `panel-${plane.alliances[0]}`);
        panelEl.style.background = this.getGradient(plane.alliances, "panel");
      } else if (!plane.location && planeEl) {
        // Delete plane
        console.log(`❌ Delete plane ${plane.id}`);
        planeEl.remove();
        // Update panel
        const panelEl = document.getElementById(`overall_player_board_${plane.id}`);
        this.swapClass(panelEl, "panel-");
        panelEl.style.background = null;
      }
    },

    movePlane(plane) {
      console.log(`✈️ Move plane ${plane.id} to ${plane.location}`);
      const rotation = this.getRotationPlane(plane);
      document.getElementById(`planeicon-${plane.id}`).style.transform = `rotate(${rotation}deg)`;
      this.swapClass(`plane-${plane.id}`, "node-", `node-${plane.location}`);
      if (spotlightPlane == plane.id) {
        this.swapClass(`spotlight-plane`, "node-", `node-${plane.location}`);
      }
    },

    renderPlaneGauges(plane) {
      let boardEl = document.getElementById(`board-${plane.id}`);
      if (boardEl == null) {
        const scoreEl = document.getElementById(`player_score_${plane.id}`);
        scoreEl.insertAdjacentHTML("beforebegin", `<span id="gps-${plane.id}" class="gps" title="${_("Current Position")}"><i class="icon gps"></i> <span id="gps-text-${plane.id}" class="gps-text">${plane.gps || ""}</span></span>`);
        const gpsEl = document.getElementById(`gps-${plane.id}`);
        gpsEl.addEventListener("click", (ev) => this.onEnterGps(plane.id));

        const parentEl = document.getElementById(`player_board_${plane.id}`);
        parentEl.insertAdjacentHTML(
          "beforeend",
          `<div id="gauges-${plane.id}" class="gauges">
  <div class="nbtag speed" title="${_("Speed")}"><i class="icon speed"></i> <span id="gauge-speed-${plane.id}"></span></div>
  <div class="nbtag seat" title="${_("Seats")}"><i class="icon seat"></i> <span id="gauge-seat-${plane.id}"></span></div>
  <div class="nbtag cash" title="${_("Cash")}"><i class="icon cash"></i> <span id="gauge-cash-${plane.id}"></span></div>
</div>
<div id="board-${plane.id}" class="plane-board">
  <div id="alliances-${plane.id}" class="alliancelist"></div>
  <div id="paxlist-${plane.id}" class="paxlist is-plane"></div>
</div>`
        );
      }
      const gaugesEl = document.getElementById(`gauges-${plane.id}`);

      // Update alliance tag
      const alliancesEl = document.getElementById(`alliances-${plane.id}`);
      if (alliancesEl.childNodes.length != plane.alliances.length) {
        alliancesEl.textContent = "";
        for (const alliance of plane.alliances) {
          alliancesEl.insertAdjacentHTML("beforeend", `<div class="nbtag alliance alliance-${alliance}" data-alliance="${alliance}"><i class="icon logo logo-${alliance}"></i> ${alliance}</div>`);
        }
        const panelEl = document.getElementById(`overall_player_board_${plane.id}`);
        panelEl.style.background = this.getGradient(plane.alliances, "panel");
        const iconEl = document.getElementById(`planeicon-${plane.id}`);
        if (iconEl) {
          iconEl.style.background = this.getGradient(plane.alliances, "plane");
        }
      }

      // Update speed tag
      const speedEl = document.getElementById(`gauge-speed-${plane.id}`);
      const speedRemain = Math.max(0, plane.speedRemain) + (plane.tempSpeed == 1 ? 1 : 0);
      speedEl.innerHTML = `<span class="${speedRemain > 0 ? "bb" : ""}">${speedRemain}</span>/${plane.speed}`;

      // Update plane speed
      const planespeedEl = document.getElementById(`planespeed-${plane.id}`);
      if (planespeedEl) {
        planespeedEl.textContent = speedRemain > 0 ? speedRemain : "0";
      }

      // Update seat tag
      const seatEl = document.getElementById(`gauge-seat-${plane.id}`);
      seatEl.innerHTML = plane.seat;

      // Update cash tag
      const cashEl = document.getElementById(`gauge-cash-${plane.id}`);
      const wallet = Object.values(plane.wallet || {}).sort();
      let cashHtml = `<span class="${plane.cashRemain > 0 ? "bb" : ""}">${plane.cashRemain}</span>`;
      if (plane.debt) {
        cashHtml += '<span class="ss"> (…)</span>';
      } else if (wallet.length > 1) {
        cashHtml += `<span class="ss"> (${wallet.join(", ")})</span>`;
      }
      cashEl.innerHTML = cashHtml;

      // Add/remove/ghost temp speed tag
      let tempSpeedEl = document.getElementById(`gauge-temp-speed-${plane.id}`);
      if (plane.tempSpeed && !tempSpeedEl) {
        gaugesEl.insertAdjacentHTML("beforeend", `<div class="nbtag speed" id="gauge-temp-speed-${plane.id}" title="${_("Temporary Speed")}"><i class="icon speed"></i> <span class="ss">${_("Temp Speed")}</span></div>`);
        tempSpeedEl = document.getElementById(`gauge-temp-speed-${plane.id}`);
      } else if (!plane.tempSpeed && tempSpeedEl) {
        tempSpeedEl.remove();
      }
      if (tempSpeedEl) {
        tempSpeedEl.classList.toggle("ghost", plane.tempSpeed == -1);
      }

      // Add/remove/ghost temp seat tag
      let tempSeatEl = document.getElementById(`gauge-temp-seat-${plane.id}`);
      if (plane.tempSeat && !tempSeatEl) {
        gaugesEl.insertAdjacentHTML("beforeend", `<div class="nbtag seat" id="gauge-temp-seat-${plane.id}" title="${_("Temporary Seat")}"><i class="icon seat"></i> <span class="ss">${_("Temp Seat")}</span></div>`);
        tempSeatEl = document.getElementById(`gauge-temp-seat-${plane.id}`);
      } else if (!plane.tempSeat && tempSeatEl) {
        tempSeatEl.remove();
      }
      if (tempSeatEl) {
        tempSeatEl.classList.toggle("ghost", plane.tempSeat == -1);
      }

      // Update GPS
      const gpsTextEl = document.getElementById(`gps-text-${plane.id}`);
      gpsTextEl.textContent = plane.gps || "";
    },

    onEnterGps(planeId) {
      const location = this.gamedatas.planes[planeId]?.location;
      if (location) {
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="spotlight-plane" class="spotlight node node-${location}"></div>`);
        spotlightPlane = planeId;
        const spotlightEl = document.getElementById(`spotlight-plane`);
        spotlightEl.addEventListener("click", (ev) => this.onLeaveGps());
        this.transitionElement(spotlightEl, (el) => (el.style.opacity = "1"));
      }
    },

    async onLeaveGps() {
      spotlightPlane = null;
      const spotlightEl = document.getElementById(`spotlight-plane`);
      if (spotlightEl) {
        await this.transitionElement(spotlightEl, (el) => (el.style.opacity = "0"));
        spotlightEl.remove();
      }
    },

    renderPlaneEmptySeats(plane) {
      const listEl = document.getElementById(`paxlist-${plane.id}`);

      // Add empty seats
      const emptyEls = listEl.querySelectorAll(".paxslot.is-empty");
      for (let i = emptyEls.length; i < plane.seatRemain; i++) {
        this.renderSlot(listEl);
      }

      // Remove empty seats
      for (let i = plane.seatRemain; i < emptyEls.length; i++) {
        console.log(`❌ Delete empty seat for plane ${plane.id}`);
        emptyEls[i].remove();
      }
    },

    renderMapManifest(manifestId, parentEl) {
      parentEl = this.getElement(parentEl);
      parentEl.insertAdjacentHTML(
        "beforeend",
        `<div id="manifest-${manifestId}" class="manifest">
  <div class="emptytxt"></div>
  <div class="location">${manifestId}</div>
  <div id="paxlist-${manifestId}" class="paxlist is-map"></div>
</div>`
      );
      if (!isMobile) {
        const manifestEl = document.getElementById(`manifest-${manifestId}`);
        manifestEl.addEventListener("mouseenter", (ev) => this.onEnterMapManifest(manifestId));
        manifestEl.addEventListener("mouseleave", (ev) => this.onLeaveMapManifest(manifestId));
      }
    },

    getSlot(pax, paxEl) {
      let destEl = null;
      if (pax.status == "SEAT" || pax.status == "SECRET" || pax.status == "PORT") {
        const listEl = document.getElementById(pax.status == "SEAT" ? `paxlist-${pax.playerId}` : `paxlist-${pax.location}`);
        if (paxEl && paxEl.parentElement.parentElement == listEl) {
          // Already in the correct destination
          destEl = paxEl.parentElement;
        } else {
          destEl = listEl.querySelector(".paxslot.is-empty");
          if (!destEl) {
            // Create a new slot in the correct destination
            this.renderSlot(listEl);
            destEl = listEl.querySelector(".paxslot.is-empty");
          }
        }
      }
      return destEl;
    },

    renderSlot(listEl) {
      console.log(`💺 Add empty seat to ${listEl.id}`);
      const emptyHtml = listEl.classList.contains("is-plane") ? `<div class="emptyseat"><i class="icon seat"></i> ${_("Empty Seat")}</div>` : "";
      listEl.insertAdjacentHTML("beforeend", `<div class="paxslot is-empty">${emptyHtml}</div>`);
    },

    renderMapNode(node) {
      if (node.length == 3) {
        // port
        this.mapEl.insertAdjacentHTML(
          "beforeend",
          `<div id="node-${node}" class="port node node-${node} is-empty">
  <div id="leadline-${node}" class="leadline"></div>
  <i class="icon people"></i><span id="nodecount-${node}" class="nodecount">0</span>
</div>`
        );
        if (!isMobile) {
          const nodeEl = document.getElementById(`node-${node}`);
          nodeEl.addEventListener("mouseenter", (ev) => this.onEnterMapManifest(node));
          nodeEl.addEventListener("mouseleave", (ev) => this.onLeaveMapManifest(node));
        }
      } else {
        // hop
        let txt = "";
        const alliance = this.gamedatas.map.nodes[node];
        if (alliance) {
          txt = this.format_string_recursive(_("Special Route: Restricted to alliance ${specialRoute}"), { specialRoute: alliance });
        }
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="node-${node}" class="hop node node-${node}" title="${txt}"></div>`);
      }
    },

    resizeMap() {
      if (this.scaleEl && this.mapEl) {
        this.scaleEl.style.setProperty("--map-width", Math.max(825, this.mapEl.clientWidth) + "px");
        this.renderMapLeads();
      }
    },

    renderMapLeads() {
      for (const airport in airportMap) {
        const leadEl = document.getElementById(`leadline-${airport}`);
        const manifestEl = document.getElementById(`paxlist-${airport}`);
        const nodeEl = document.getElementById(`node-${airport}`);
        if (leadEl && manifestEl && nodeEl) {
          const manifestPos = manifestEl.getBoundingClientRect();
          const parentId = manifestEl.parentElement.parentElement.id;
          const nodePos = nodeEl.getBoundingClientRect();
          const nodeLeftMid = nodePos.left + nodePos.width / 2;
          const isBetweenLeft = nodeLeftMid >= manifestPos.left && nodeLeftMid <= manifestPos.left + manifestPos.width;

          if (parentId == "manifests-top") {
            // straight lead up
            let height = nodePos.top - manifestPos.top - manifestPos.height - 6;
            leadEl.style.height = `${height}px`;
            leadEl.style.width = "0px";
            leadEl.style.top = null;
            leadEl.style.bottom = `${nodePos.height}px`;
            leadEl.style.left = null;
            leadEl.style.right = null;
          } else if (parentId == "manifests-bottom") {
            let height = manifestPos.top - nodePos.top - nodePos.height - 6;
            let width = 0;
            if (isBetweenLeft) {
              // straight lead down
              leadEl.style.height = `${height}px`;
              leadEl.style.width = "0px";
              leadEl.style.top = `${nodePos.height}px`;
              leadEl.style.bottom = null;
              leadEl.style.left = null;
              leadEl.style.right = null;
              leadEl.classList.toggle("curved", false);
            } else {
              // curved lead down
              height += nodePos.height / 2;
              if (manifestPos.left > nodePos.left) {
                // curved lead right down
                width = manifestPos.left + manifestPos.width * 0.15 - nodePos.left - nodePos.width - 6;
                leadEl.style.left = `${nodePos.width}px`;
                leadEl.style.right = null;
                leadEl.classList.toggle("left", false);
              } else {
                // curved to the left down (DFW)
                width = nodePos.left - manifestPos.left - manifestPos.width * 0.85 - 6;
                leadEl.style.left = null;
                leadEl.style.right = `${nodePos.width}px`;
                leadEl.classList.toggle("left", true);
              }
              leadEl.style.height = `${height}px`;
              leadEl.style.width = `${width}px`;
              leadEl.style.top = `${nodePos.height / 2}px`;
              leadEl.style.bottom = null;
              leadEl.classList.toggle("curved", true);
            }
          } else if (parentId == "manifests-right") {
            let height = manifestPos.top - nodePos.top - nodePos.height / 2 - 6;
            if (height > 4) {
              // curved lead right + down
              let height = manifestPos.top - nodePos.top - nodePos.height / 2 - 6;
              let width = manifestPos.left + manifestPos.width * 0.25 - nodePos.left - nodePos.width - 6;
              leadEl.style.height = `${height}px`;
              leadEl.style.width = `${width}px`;
              leadEl.style.top = `${nodePos.height / 2}px`;
              leadEl.style.bottom = null;
              leadEl.style.left = `${nodePos.width}px`;
              leadEl.style.right = null;
              leadEl.classList.toggle("curved", true);
            } else {
              // straight lead right
              let width = manifestPos.left - nodePos.left - nodePos.width - 6;
              leadEl.style.height = "0px";
              leadEl.style.width = `${width}px`;
              leadEl.style.top = null;
              leadEl.style.bottom = null;
              leadEl.style.left = `${nodePos.width}px`;
              leadEl.style.right = null;
              leadEl.classList.toggle("curved", false);
            }
          }
        }
      }
    },

    renderMapCounts() {
      const pax = Object.values(this.gamedatas.pax).filter((x) => x.status == "PORT" || x.status == "SECRET");
      for (const airport in airportMap) {
        const paxCount = pax.filter((x) => x.location == airport).length;
        const isAngry = pax.some((x) => x.location == airport && x.anger == 3);
        const manifestEl = document.getElementById(`manifest-${airport}`);
        if (manifestEl) {
          manifestEl.classList.toggle("is-anger", isAngry);
          const emptyEl = manifestEl.querySelector(".emptytxt");
          emptyEl.textContent = paxCount == 0 ? this.format_string_recursive(_("No passengers in ${city}"), { city: _(airportMap[airport]) }) : "";
        }
        const nodeEl = document.getElementById(`node-${airport}`);
        if (nodeEl) {
          nodeEl.classList.toggle("is-anger", isAngry);
          nodeEl.classList.toggle("is-empty", paxCount == 0);
          nodeEl.classList.toggle("pinging-anger", isAngry);
          const countEl = document.getElementById(`nodecount-${airport}`);
          if (countEl) {
            countEl.textContent = paxCount;
          }
        }
      }
    },

    onEnterMapManifest(manifestId) {
      const manifestEl = document.getElementById(`manifest-${manifestId}`);
      manifestEl.classList.add("is-active");
      const nodeEl = document.getElementById(`node-${manifestId}`);
      nodeEl.classList.add("is-active", "pinging");
    },

    onLeaveMapManifest(manifestId) {
      const manifestEl = document.getElementById(`manifest-${manifestId}`);
      manifestEl.classList.remove("is-active");
      const nodeEl = document.getElementById(`node-${manifestId}`);
      nodeEl.classList.remove("is-active", "pinging");
    },

    async renderPax(pax) {
      console.log(`🧑 Render pax ${pax.id}: location=${pax.location}, playerId=${pax.playerId}, status=${pax.status}`);

      // Determine where the pax is and where it belongs
      let paxEl = document.getElementById(`pax-${pax.id}`);
      let destEl = this.getSlot(pax, paxEl);

      if (!destEl) {
        // Pax shouldn't exist
        if (paxEl) {
          this.deletePax(pax);
        }
        return;
      }

      if (!paxEl) {
        // Pax doesn't exist but should
        // Create new pax
        destEl.insertAdjacentHTML(
          "beforeend",
          `<div id="pax-${pax.id}" class="pax">
  <div class="anger">
    <div class="icon"></div>
    <div class="count"></div>
  </div>
  <div class="vip"></div>
  <div class="ticket">
    <span class="origin">${pax.origin} » </span>
    <span class="destination"></span>
    <div class="cash"></div>
  </div>
</div>`
        );
        paxEl = document.getElementById(`pax-${pax.id}`);
        paxEl.addEventListener("click", () => this.takePaxAction(pax.id));
      }

      // Update the attributes
      const angerEl = paxEl.querySelector(".anger");
      const angerIconEl = angerEl.querySelector(".icon");
      const angerCountEl = angerEl.querySelector(".count");
      const vipEl = paxEl.querySelector(".vip");
      const destinationEl = paxEl.querySelector(".destination");
      const cashEl = paxEl.querySelector(".cash");

      if (pax.status == "SECRET") {
        paxEl.classList.add("is-secret");
        destinationEl.textContent = "???";
      } else {
        paxEl.classList.remove("is-secret");
        cashEl.textContent = `$${pax.cash}`;
        destinationEl.textContent = pax.destination;
      }
      this.swapClass(angerEl, "anger-", `anger-${pax.anger}`);
      angerCountEl.textContent = pax.anger;
      if (pax.vipInfo) {
        this.swapClass(angerIconEl, ["anger-", "vipstar"], "vipstar");
        let vipName = _(pax.vipInfo.name);
        let vipDesc = _(pax.vipInfo.name) + ": " + _(pax.vipInfo.desc);
        if (pax.vipInfo.args) {
          vipName = this.format_string_recursive(vipName, pax.vipInfo.args);
          vipDesc = this.format_string_recursive(vipDesc, pax.vipInfo.args);
          vipEl.innerHTML = vipDesc;
          vipDesc = vipEl.textContent;
        }
        paxEl.title = vipDesc;
        vipEl.innerHTML = vipName;
      } else {
        this.swapClass(angerIconEl, ["anger-", "vipstar"], `anger-${pax.anger}`);
        paxEl.title = "";
        vipEl.textContent = "";
      }

      // Move the pax (if necessary)
      this.movePax(paxEl, destEl);
    },

    async movePax(paxEl, destEl) {
      destEl.classList.remove("is-empty");
      if (paxEl.parentElement.parentElement == destEl.parentElement) {
        // Already correct, nothing to do
        return;
      }
      destEl.classList.add("is-moving");
      paxEl.parentElement.classList.add("is-empty");

      // Record start position
      const start = paxEl.getBoundingClientRect();

      // Make a copy and put it at start
      const cloneEl = paxEl.cloneNode(true);
      cloneEl.id += "-clone";
      this.positionElement(cloneEl, start);
      document.body.appendChild(cloneEl);

      // Move and record end position
      destEl.appendChild(paxEl);
      const end = paxEl.getBoundingClientRect();
      paxEl.style.opacity = "0";

      // Animate the copy to the end
      await this.translateElement(cloneEl, start, end);
      paxEl.style.opacity = null;
      cloneEl.remove();
      destEl.classList.remove("is-moving");
    },

    async deletePax(pax) {
      console.log(`❌ Delete passenger ${pax.id} (${pax.status})`);
      const paxEl = document.getElementById(`pax-${pax.id}`);
      if (!paxEl) {
        return;
      }
      paxEl.parentElement.classList.add("is-empty");

      // Record start and end position
      let endEl = null;
      if (pax.status == "CASH") {
        endEl = document.getElementById(`gauges-${pax.playerId}`);
      } else if (pax.status == "COMPLAINT") {
        endEl = document.getElementById("nbcommon");
      }
      if (!endEl) {
        paxEl.remove();
        return;
      }
      const start = paxEl.getBoundingClientRect();
      const end = endEl.getBoundingClientRect();

      // Make a copy and put it at start
      const cloneEl = paxEl.cloneNode(true);
      cloneEl.id += "-clone";
      this.positionElement(cloneEl, start);
      document.body.appendChild(cloneEl);

      // Delete the pax
      paxEl.remove();

      // Animate the copy to the end
      await this.translateElement(cloneEl, start, end);
      cloneEl.remove();
    },

    renderBuys() {
      let buysEl = document.getElementById("nbbuys");
      if (!buysEl) {
        const parentEl = document.getElementById("maintitlebar_content");
        parentEl.insertAdjacentHTML("beforeend", `<div id="nbwrap"><div id="nbbuys"></div></div>`);
        buysEl = document.getElementById("nbbuys");
      } else {
        buysEl.textContent = "";
      }
      for (const i in this.buys) {
        const buy = this.buys[i];
        const id = `button_buy_${i}`;
        let cssClass = "buybutton";
        let icon = "";
        let txt = "";
        if (buy.type == "ALLIANCE") {
          cssClass = `alliance alliance-${buy.alliance}`;
          icon = `<div class="icon logo logo-${buy.alliance}"></div>`;
          txt = buy.alliance;
        } else if (buy.type == "SEAT") {
          icon = '<div class="icon seat"></div>';
          txt = _("Seat") + " " + buy.seat;
        } else if (buy.type == "TEMP_SEAT") {
          icon = '<div class="icon seat"><div class="icon temp"></div></div>';
          txt = _("Temporary Seat");
        } else if (buy.type == "SPEED") {
          icon = '<div class="icon speed"></div>';
          txt = _("Speed") + " " + buy.speed;
        } else if (buy.type == "TEMP_SPEED") {
          icon = '<div class="icon speed"><div class="icon temp"></div></div>';
          txt = _("Temporary Speed");
        }
        if (buy.cost > 0) {
          txt += ` ($${buy.cost})`;
        }
        if (!(buy.enabled && buy.ownerId == null)) {
          cssClass += " ghostbutton";
        }
        let buyHtml = `<div id="${id}" class="action-button bgabutton ${cssClass}">${icon}${txt}</div>`;
        if (buy.ownerId) {
          const owner = this.gamedatas.players[buy.ownerId];
          buyHtml = `<div class="nbuttonwrap">${buyHtml}<div class="owner" style="color:#${owner.color};">${owner.name}</div></div>`;
        }
        buysEl.insertAdjacentHTML("beforeend", buyHtml);
        const buyEl = document.getElementById(id);
        if (buy.enabled && buy.ownerId == null) {
          buyEl.addEventListener("click", (ev) => this.takeAction("buy", buy));
        }
        if (buy.type == "ALLIANCE" && !isMobile) {
          buyEl.addEventListener("mouseenter", (ev) => this.onEnterMapManifest(buy.alliance));
          buyEl.addEventListener("mouseleave", (ev) => this.onLeaveMapManifest(buy.alliance));
        }
      }
    },

    renderLedger(args) {
      const walletHtml = args.wallet.map((cash) => `<div class="nbtag cash"><i class="icon cash"></i> ${cash}</div>`).join("");
      const walletSum = args.wallet.reduce((a, b) => a + b, 0);
      let ledgerHtml = "";
      if (args.ledger?.length > 0) {
        for (const l of args.ledger) {
          let txt = l.type;
          if (l.type == "ALLIANCE") {
            txt = this.format_string_recursive(_("Purchase alliance ${alliance}"), { alliance: l.arg });
          } else if (l.type == "SEAT") {
            txt = this.format_string_recursive(_("Purchase seat ${seat}"), { seat: l.arg });
          } else if (l.type == "SPEED") {
            txt = this.format_string_recursive(_("Purchase speed ${speed}"), { speed: l.arg });
          } else if (l.type == "TEMP_SEAT") {
            txt = this.format_string_recursive(_("Purchase ${temp}"), { temp: _("Temp Seat"), tempIcon: "seat" });
          } else if (l.type == "TEMP_SPEED") {
            txt = this.format_string_recursive(_("Purchase ${temp}"), { temp: _("Temp Speed"), tempIcon: "speed" });
          }
          ledgerHtml += `<tr><td class="lline">${txt}</td><td class="lline lamt lneg">($${l.cost})</td></tr>`;
        }
        ledgerHtml += `<tr><td class="lsubtotal">${_("Cash available")}</td><td class="lsubtotal lamt">$${args.cash}&nbsp;</tr>`;
        if (args.overpay) {
          ledgerHtml += `<tr><td class="lneg">${_("Estimated overpayment")}</td><td class="lamt lneg">($${args.overpay})</tr>`;
        }
      }
      const parentEl = document.getElementById("nbwrap");
      parentEl.insertAdjacentHTML(
        "beforeend",
        `<table id="nbledger">
  <tr><td colspan="2" class="lhead">* ${_("Account Balance")} *</td></tr>
  <tr><td class="lline">${_("Cash")} ${walletHtml}</td><td class="lline lamt">$${walletSum}&nbsp;</td></tr>
  ${ledgerHtml}
  <tr><td colspan="2" class="lhead"></td></tr>
</table>`
      );
    },

    renderWalletPay(wallet, suggestion) {
      const parentEl = document.getElementById("generalactions");
      parentEl.insertAdjacentHTML("beforeend", `<div id="nbbuys"></div>`);
      const buysEl = document.getElementById("nbbuys");
      const workingSuggestion = [...suggestion];
      let i = 0;
      for (const cash of wallet) {
        const id = `pay_${i++}`;
        let cssClass = "ghostbutton";
        const index = workingSuggestion.indexOf(cash);
        if (index > -1) {
          cssClass = "";
          workingSuggestion.splice(index, 1);
        }
        buysEl.insertAdjacentHTML("beforeend", `<div id="${id}" class="action-button bgabutton paybutton ${cssClass}" data-cash="${cash}"><i class="icon cash"></i> ${cash}</div>`);
        const buyEl = document.getElementById(id);
        buyEl.addEventListener("click", (ev) => {
          buyEl.classList.toggle("ghostbutton");
          this.updatePayButton();
        });
      }
      this.updatePayButton();
    },

    updatePayButton() {
      let sum = 0;
      document.querySelectorAll("#nbbuys .paybutton:not(.ghostbutton)").forEach((el) => (sum += parseInt(el.dataset.cash)));
      const buttonEl = document.getElementById("button_pay");
      buttonEl.textContent = `${_("Pay")} $${sum}`;
    },

    renderMoves(moves) {
      const plane = this.gamedatas.planes[this.player_id];
      const alliance = plane.alliances[0];
      let moveCount = 0;
      for (const i in moves) {
        const move = moves[i];
        move.alliance = this.gamedatas.map.nodes[move.location];
        let specialHtml = "";
        if (move.alliance) {
          const specialTxt = this.format_string_recursive(_("Special Route: Restricted to alliance ${specialRoute}"), { specialRoute: move.alliance });
          specialHtml = `<div class="specialtag alliance-${move.alliance}" title="${specialTxt}"><i class="icon logo logo-${move.alliance}"></i></div>`;
          const weatherEl = document.getElementById(`weather-${move.location}`);
          if (weatherEl) {
            weatherEl.classList.add("hidespecial");
          }
        }
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="move-${move.location}" class="move node node-${move.location} gradient-${alliance}">${move.fuel}${specialHtml}</div>`);
        const moveEl = document.getElementById(`move-${move.location}`);
        moveEl.addEventListener("click", (e) => this.takeMoveAction(plane, move));
        moveCount++;
      }
      console.log(`🗺️ Add ${moveCount} possible moves`);
    },

    deleteMoves() {
      console.log(`❌ Delete possible moves`);
      document.querySelectorAll("#nbmap .move").forEach((el) => el.remove());
      document.querySelectorAll(".weather.node.hidespecial").forEach((el) => el.classList.remove("hidespecial"));
    },

    renderTitleMessage(msg) {
      let msgEl = document.getElementById("nbmsg");
      if (!msg) {
        msgEl?.remove();
        return;
      }
      const msgHtml = this.format_string_recursive(msg.log, msg);
      if (!msgEl) {
        const parentEl = document.getElementById("page-title");
        parentEl.insertAdjacentHTML("beforeend", `<div id="nbmsg">${msgHtml}</div>`);
      } else {
        msgEl.innerHTML = msgHtml;
      }
    },

    getGradient(alliances, context) {
      if (alliances.length <= 1) {
        return null;
      }
      let background, position, width;
      if (context == "plane") {
        position = 40;
        width = 20 / (alliances.length - 1);
        background = `conic-gradient(transparent ${position}%`;
      } else {
        position = 66;
        width = 33 / (alliances.length - 1);
        background = `linear-gradient(135deg, transparent ${position}%`;
      }
      for (let i = 1; i < alliances.length; i++) {
        background += `, var(--alliance-${alliances[i]}) ${position}% ${position + width}%`;
        position += width;
      }
      if (position < 100) {
        background += `, transparent ${position}%`;
      }
      background += `), var(--alliance-${alliances[0]})`;
      return background;
    },

    renderFlightPlans(sort) {
      if (!this.gamedatas.plans?.length) {
        return;
      }
      const rows = [];
      for (const plan of this.gamedatas.plans) {
        const [destination, alliance, id, time, moves] = plan;
        const city = _(airportMap[destination]);
        let order,
          status,
          statusClass = "";
        if (moves == 0) {
          status = _("On Time");
        } else if (moves > 0) {
          status = _("Delayed");
          statusClass = "slow";
        } else {
          status = _("Early");
          statusClass = "fast";
        }
        if (sort == "time") {
          order = `${time}.${city}`;
        } else if (sort == "flight") {
          order = `${alliance}.${String(id).padStart(3, "0")}`;
        } else if (sort == "status") {
          order = `${status}.${city}.${time}`;
        } else {
          order = `${city}.${time}`;
        }

        const html = `<tr>
  <td>${city}</td>
  <td><span class="nbtag alliance alliance-${alliance}"><i class="icon logo logo-${alliance}"></i> ${id}</span></td>
  <td>${time}</td>
  <td class="${statusClass}">${status}</td>
</tr>`;
        rows.push({ html, order });
      }
      rows.sort((a, b) => (a.order > b.order ? 1 : -1));

      const tables = [];
      const chunkSize = Math.ceil(rows.length / 2);
      for (let i = 0; i < rows.length; i += chunkSize) {
        tables.push(
          `<table>
  <tr>
    <th>${_("Destination")}</th>
    <th data-sort="flight">${_("Flight")}</th>
    <th data-sort="time">${_("Time")}</th>
    <th data-sort="status">${_("Status")}</th>
  </tr>` +
            rows
              .slice(i, i + chunkSize)
              .map((row) => row.html)
              .join("") +
            "</table>"
        );
      }

      let plansEl = document.getElementById("nbplans");
      if (!plansEl) {
        const resultEl = document.getElementById("pagesection_gameresult");
        resultEl.insertAdjacentHTML("afterbegin", `<div id='nbdepartures'>${_("Departures")}</div><div id='nbplans'></div>`);
        plansEl = document.getElementById("nbplans");
      }
      plansEl.innerHTML = tables.join("");
      document.querySelectorAll("#nbplans th").forEach((th) => {
        th.addEventListener("click", () => this.renderFlightPlans(th.dataset.sort));
      });
    },
  });
});
