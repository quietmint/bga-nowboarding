define(["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter"], function (dojo, declare) {
  const playSoundSuper = window.playSound;

  let lastErrorCode = null;
  let suppressSounds = [];
  let flyTimer = null;

  return declare("bgagame.nowboarding", ebg.core.gamegui, {
    constructor() {
      dojo.place("loader_mask", "overall-content", "before");
    },

    setup(gamedatas) {
      console.log("🐣 Setup", gamedatas);

      // Setup common
      this.renderCommon();

      // Setup map
      const playerCount = Object.keys(gamedatas.players).length;
      this.mapEl = document.getElementById("nbmap");
      this.mapEl.classList.add(playerCount >= 4 ? "map45" : "map23");
      const manifestContainer = {
        SEA: "manifests-top",
        SFO: playerCount >= 4 ? "manifests-bottom" : "manifests-top",
        DEN: "manifests-top",
        ORD: "manifests-top",
        JFK: "manifests-top",
        ATL: playerCount >= 4 ? "manifests-right" : "manifests-bottom",
        LAX: "manifests-bottom",
        DFW: "manifests-bottom",
        MIA: "manifests-bottom",
      };
      for (const node of gamedatas.map.nodes) {
        this.renderMapNode(node);
        if (node.length == 3) {
          this.renderMapManifest(node, manifestContainer[node]);
        }
      }
      for (const location in gamedatas.map.weather) {
        const token = gamedatas.map.weather[location];
        this.createWeather(location, token);
      }

      // Setup planes
      if (gamedatas.planes) {
        for (const planeId in gamedatas.planes) {
          const plane = gamedatas.planes[planeId];
          this.renderPlane(plane);
          this.renderPlaneGauges(plane);
          this.renderPlaneManifest(plane);
        }
      }

      // Setup pax
      if (gamedatas.pax) {
        for (const paxId in gamedatas.pax) {
          const pax = gamedatas.pax[paxId];
          this.renderPax(pax);
        }
        this.renderWarnings();
      }

      // Setup sounds
      window.playSound = function (sound) {
        console.log("🔊 Asked to play sound", sound);
        const index = suppressSounds.indexOf(sound);
        if (index > -1) {
          suppressSounds.splice(index, 1);
          console.warn("NO!!! suppress sound", sound);
        } else {
          playSoundSuper(sound);
        }
      };

      // Setup notifications
      dojo.subscribe("buildPrimary", this, "onNotify");
      dojo.subscribe("buys", this, "onNotify");
      dojo.subscribe("complaint", this, "onNotify");
      dojo.subscribe("flyTimer", this, "onNotify");
      dojo.subscribe("hour", this, "onNotify");
      dojo.subscribe("move", this, "onNotify");
      dojo.subscribe("pax", this, "onNotify");
      dojo.subscribe("planes", this, "onNotify");
      dojo.subscribe("sound", this, "onNotify");
      dojo.subscribe("weather", this, "onNotify");
      this.notifqueue.setSynchronous("complaint", 2000);
      this.notifqueue.setSynchronous("flyTimer", 5500);

      // Setup preferences
      this.setupPrefs();

      // Production bug report handler
      dojo.subscribe("loadBug", this, function loadBug(n) {
        function fetchNextUrl() {
          const url = n.args.urls.shift();
          console.log("Fetching URL", url);
          dojo.xhrGet({
            url: url,
            load(success) {
              console.log("Success for URL", url, success);
              if (n.args.urls.length > 0) {
                fetchNextUrl();
              } else {
                console.log("Done, reloading page");
                window.location.reload();
              }
            },
          });
        }
        console.log("Notif: load bug", n.args);
        fetchNextUrl();
      });

      function debounce(func, timeout) {
        let timer;
        return (...args) => {
          clearTimeout(timer);
          timer = setTimeout(() => {
            func.apply(this, args);
          }, timeout);
        };
      }

      // const testEl = document.getElementById("testview");
      // const testHandler = (e) => {
      //   const pos = dojo.position("NMap");
      //   var x = Math.round((e.offsetX / pos.w) * 1000) / 10 + "%";
      //   var y = Math.round((e.offsetY / pos.h) * 1000) / 10 + "%";
      //   dojo.style("testpoint", {
      //     left: x,
      //     top: y,
      //   });
      //   testEl.textContent = "left: " + x + "; top: " + y + ";";
      //   if (e.type == "click") {
      //     console.log(testEl.textContent);
      //     navigator.clipboard.writeText(testEl.textContent);
      //   }
      // };
      // this.mapEl.addEventListener("mousemove", debounce(testHandler, 50));
      // this.mapEl.addEventListener("click", debounce(testHandler, 50));
    },

    setupPrefs() {
      // Extract the ID and value from the UI control
      const _this = this;
      function onchange(e) {
        const match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
        if (!match) {
          return;
        }
        const id = +match[1];
        const value = +e.target.value;
        _this.prefs[id].value = value;
        _this.onPrefChange(id, value);
      }

      // Call onPrefChange() when any value changes
      dojo.query(".preference_control").connect("onchange", onchange);

      // Call onPrefChange() now
      dojo.forEach(dojo.query("#ingame_menu_content .preference_control"), function (el) {
        onchange({ target: el });
      });
    },

    // ----------------------------------------------------------------------

    /* @Override */
    format_string_recursive(log, args) {
      if (log && args && !args.processed) {
        for (const k in args) {
          if (k == "alliance") {
            args[k] = `<span class="nbtag alliance-${args[k]}"><i class="icon logo-${args[k]}"></i> ${args[k]}</span>`;
          } else if (k == "anger") {
            args[k] = `<span class="nbtag anger-${args[k]}"><i class="icon anger-${args[k]}"></i> ${args[k]}</span>`;
          } else if (k == "cash") {
            args[k] = `<span class="nbtag cash"><i class="icon cash"></i> ${args[k]}</span>`;
          } else if (k == "complaint") {
            args[k] = `<span class="nbtag complaint"><i class="icon complaint"></i> ${args[k]}</span>`;
          } else if (k == "seat") {
            args[k] = `<span class="nbtag seat"><i class="icon seat"></i> ${args[k]}</span>`;
          } else if (k == "speed") {
            args[k] = `<span class="nbtag speed"><i class="icon speed"></i> ${args[k]}</span>`;
          } else if (k == "temp") {
            args[k] = `<span class="nbtag ${args.tempIcon}"><i class="icon ${args.tempIcon}"></i> ${args[k]}</span>`;
          } else if (k == "vip") {
            args[k] = `<span class="nbtag vip"><i class="icon vipstar"></i> ${args[k]}</span>`;
          } else if (k == "location" || k == "route" || k == "routeFast" || k == "routeSlow") {
            // Generic "bold" text
            args[k] = `<b>${args[k]}</b>`;
          }
        }
        args.processed = true;
      }
      return this.inherited(arguments);
    },

    /* @Override */
    showMessage(msg, type) {
      if (type == "error") {
        lastErrorCode = msg.startsWith("!!!") ? msg.substring(3) : null;
        if (lastErrorCode) {
          return;
        }
      }
      this.inherited(arguments);
    },

    /* @Override */
    onScreenWidthChange() {
      // Remove broken "zoom" property added by BGA framework
      this.gameinterface_zoomFactor = 1;
      dojo.style("page-content", "zoom", "");
      dojo.style("page-title", "zoom", "");
      dojo.style("right-side-first-part", "zoom", "");
      this.computeViewport();
    },

    computeViewport() {
      // Force device-width during chat
      var chatVisible = false;
      for (var w in this.chatbarWindows) {
        if (this.chatbarWindows[w].status == "expanded") {
          chatVisible = true;
          break;
        }
      }

      // Force mobile view in landscape orientation
      var landscape = window.orientation === -90 || window.orientation === 90;
      var width = chatVisible ? "device-width" : 1150;
      this.interface_min_width = width;
      this.default_viewport = "width=" + width;
      return this.default_viewport;
    },

    /* @Override */
    expandChatWindow: function () {
      this.inherited(arguments);
      dojo.query('meta[name="viewport"]')[0].content = this.computeViewport();
    },

    /* @Override */
    collapseChatWindow: function () {
      this.inherited(arguments);
      dojo.query('meta[name="viewport"]')[0].content = this.computeViewport();
    },

    /* @Override */
    // adaptPlayersPanels: function () {
    //   if (dojo.hasClass("ebd-body", "mobile_version")) {
    //     dojo.style("left-side", "marginTop", dojo.position("right-side").h + "px");
    //   } else {
    //     dojo.style("left-side", "marginTop", "0px");
    //   }
    // },

    /* @Override */
    updatePlayerOrdering() {
      this.inherited(arguments);
      dojo.place("nbcommon", "player_boards", "first");
    },

    /* @Override */
    updateReflexionTime() {
      this.inherited(arguments);
      if (this.gamedatas.gamestate.name == "fly" && this.gamedatas.gamestate.args.endTime) {
        const countdownEl = document.getElementById("nbcountdown");
        if (countdownEl) {
          const seconds = this.gamedatas.gamestate.args.endTime - Date.now() / 1000;
          const f = this.formatReflexionTime(seconds);
          countdownEl.textContent = f.string;
        }
      }
    },

    // ----------------------------------------------------------------------

    onEnteringState(stateName, args) {
      console.log("onEnteringState", stateName, args);
      if (stateName == "fly" && args.args.endTime) {
        // Add action bar countdown
        document.body.classList.remove("no_time_limit");
        const destEl = document.getElementById("page-title");
        destEl.insertAdjacentHTML("afterbegin", '<div id="nbcountdown"></div>');
        // Start timer
        if (flyTimer) {
          window.clearTimeout(flyTimer);
        }
        const millis = Math.max(0, args.args.endTime * 1000 - Date.now()) + Math.random() * 5000;
        console.log("START THE TIMER", millis);
        flyTimer = window.setTimeout(() => {
          this.takeAction("flyTimer", { lock: false });
        }, millis);
      } else if (stateName == "maintenance") {
        // Remove action bar countdown
        if (this.gamedatas.noTimeLimit) {
          document.body.classList.add("no_time_limit");
        }
        const countdownEl = document.getElementById("nbcountdown");
        if (countdownEl) {
          countdownEl.remove();
        }
        // Stop timer
        if (flyTimer) {
          window.clearTimeout(flyTimer);
        }
      }
    },

    onUpdateActionButtons(stateName, args) {
      console.log(`▶️ State ${stateName}`, args);

      if (!this.is_spectator) {
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
            this.addActionButton("button_prepareDone", _("Ready"), () => this.takeAction("prepareDone"));
            if (this.gamedatas.vip) {
              if (this.gamedatas.hour.vipNew) {
                const txt = this.format_string_recursive(_("Decline VIP (${count} remaining)"), { count: this.gamedatas.hour.vipRemain });
                this.addActionButton("button_vip", txt, () => this.takeVipAction());
              } else if (this.gamedatas.hour.vipRemain) {
                const txt = this.format_string_recursive(_("Accept VIP (${count} remaining)"), { count: this.gamedatas.hour.vipRemain });
                this.addActionButton("button_vip", txt, () => this.takeVipAction());
              }
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
              this.addActionButton("button_flyDoneSnooze", _("Snooze Until Next Move"), () => this.takeAction("flyDone", { snooze: true }), null, false, "gray");
            }
            if (args.speedRemain > 0) {
              const txt = this.format_string_recursive(_("End Round Early (${speedRemain} speed remaining)"), { speedRemain: args.speedRemain });
              this.addActionButton("button_flyDone", txt, () => this.takeAction("flyDone"), null, false, "red");
            } else {
              this.addActionButton("button_flyDone", _("End Round"), () => this.takeAction("flyDone"), null, false, "blue");
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
        if (notif.args.state != this.gamedatas.gamestate.private_state?.name) {
          console.warn("Ignore buys notification because we are in the wrong state");
          return;
        }
        for (const newBuy of notif.args.buys) {
          for (const buy of this.buys) {
            if (buy.type == newBuy.type && buy.alliance == newBuy.alliance) {
              console.log("updated the owner", buy, newBuy);
              buy.ownerId = newBuy.ownerId;
              break;
            }
          }
        }
        this.renderBuys();
      } else if (notif.type == "complaint") {
        suppressSounds = ["yourturn"];
        playSound("nowboarding_complaint");
        this.gamedatas.complaint = notif.args.total;
        this.renderCommon();
      } else if (notif.type == "flyTimer") {
        suppressSounds = ["yourturn"];
        playSound("nowboarding_chime");
        this.showMessage(_("Time is up!") + '<div class="flyTimer"><i class="icon timer"></i></div>', "info");
        this.deleteMoves();
      } else if (notif.type == "hour") {
        this.gamedatas.hour = notif.args;
        if (this.gamedatas.hour.hour == "FINALE") {
          suppressSounds = ["yourturn"];
          playSound("nowboarding_walkway");
        }
        if ((this.gamedatas.hour.vipNew || this.gamedatas.hour.vipRemain) && this.gamedatas.gamestate.private_state?.name == "prepareBuy") {
          console.log("change the vip button", notif.args.vipNew);
          const vipEl = document.getElementById("button_vip");
          if (vipEl) {
            vipEl.textContent = this.format_string_recursive(this.gamedatas.hour.vipNew ? _("Decline VIP (${count} remaining)") : _("Accept VIP (${count} remaining)"), { count: this.gamedatas.hour.vipRemain });
          }
        }
        this.renderCommon();
      } else if (notif.type == "move") {
        if (notif.args.player_id == this.player_id) {
          // Erase possible moves
          this.deleteMoves();
        }
        this.movePlane(notif.args.plane);
        this.renderPlaneGauges(notif.args.plane);
      } else if (notif.type == "pax") {
        for (const pax of notif.args.pax) {
          this.gamedatas.pax[pax.id] = pax;
        }
        for (const pax of notif.args.pax) {
          this.renderPax(pax);
        }
        this.renderWarnings();
      } else if (notif.type == "planes") {
        for (const plane of notif.args.planes) {
          this.gamedatas.planes[plane.id] = plane;
          this.renderPlane(plane);
          this.renderPlaneGauges(plane);
          this.renderPlaneManifest(plane);
        }
      } else if (notif.type == "sound") {
        suppressSounds = notif.args.suppress;
        // this.disableNextMoveSound();
        playSound(`nowboarding_${notif.args.sound}`);
      } else if (notif.type == "weather") {
        suppressSounds = ["yourturn"];
        playSound("nowboarding_plane");
        this.deleteWeather();
        for (const location in notif.args.weather) {
          const token = notif.args.weather[location];
          this.createWeather(location, token);
        }
      }
    },

    onPrefChange(id, value) {
      console.log("Preference changed", id, value);
    },

    // Utilities
    // ----------------------------------------------------------------------

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
        const start = Date.now();
        console.log(`👆 Take action ${action}`, data);
        this.ajaxcall(
          "/nowboarding/nowboarding/" + action + ".html",
          data,
          this,
          () => {},
          (error) => {
            const duration = Date.now() - start;
            if (error) {
              console.error(`Take action ${action} error in ${duration}ms`, lastErrorCode);
              reject(lastErrorCode);
            } else {
              console.log(`Take action ${action} done in ${duration}ms`);
              resolve();
            }
          }
        );
      });
    },

    takePaxAction(paxId) {
      if (this.isCurrentPlayerActive()) {
        const pax = this.gamedatas.pax[paxId];
        if (pax.status == "SEAT" && pax.playerId == this.player_id) {
          this.takeAction("deplane", { paxId: Math.abs(pax.id) });
        } else {
          this.takeAction("board", { paxId: Math.abs(pax.id) });
        }
      }
    },

    takeVipAction() {
      this.takeAction("vip", { accept: !this.gamedatas.hour.vipNew });
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

    getRotation(plane) {
      if (!plane.location || !plane.origin) {
        return null;
      }
      const locationPos = dojo.position(`node-${plane.location}`);
      const originPos = dojo.position(`node-${plane.origin}`);
      if (!locationPos || !originPos) {
        return null;
      }
      let rotation = (Math.atan2(locationPos.y - originPos.y, locationPos.x - originPos.x) * 180) / Math.PI + 90;
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

    translateElement(el, start, end) {
      return new Promise((resolve) => {
        const fallback = setTimeout(resolve, 1100);
        el.addEventListener("transitionend", () => {
          resolve();
          clearTimeout(fallback);
        });
        el.offsetHeight; // repaint
        el.style.transform = `translate(${end.x - start.x}px, ${end.y - start.y}px)`;
      });
    },

    // Rendering
    // ----------------------------------------------------------------------

    renderCommon() {
      let commonEl = document.getElementById("nbcommon");
      if (!commonEl) {
        let commonHtml = `<div id="nbcommon" class="player-board gauges">
  <div class="nbsection">
    <div class="nblabel">${_("Complaints")}</div>
    <div class="nbtag"><i class="icon complaint"></i> <span id="nbcommon-complaint"></span></div>
  </div>
  <div class="nbsection">
    <div class="nblabel">${_("Round")}</div>
    <div class="nbtag hour"><i class="icon"></i> <span></span></div>
  </div>`;
        if (this.gamedatas.vip) {
          commonHtml += `<div class="nbsection">
  <div class="nblabel">${_("VIPs Remain")}</div>
  <div class="nbtag"><i class="icon vipstar"></i> <span id="nbcommon-vip"></span></div>
</div>`;
        }
        commonHtml += `<div class="nbsection">
    <div class="nblabel">${_("Map Size")}</div>
    <div class="nbtag"><input type="range" id="nbrange" min="40" max="100" step="2" value="100"></div>
  </div>
</div>`;
        const parentEl = document.getElementById("player_boards");
        parentEl.insertAdjacentHTML("afterbegin", commonHtml);
        commonEl = document.getElementById("nbcommon");

        const nbscale = document.getElementById("nbscale");
        const nbrange = document.getElementById("nbrange");
        nbrange.addEventListener("input", (ev) => {
          nbscale.style.width = `${nbrange.value}%`;
          try {
            localStorage.setItem("nowboarding.scale", nbrange.value);
          } catch (e) {
            // Local storage unavailable
          }
        });
        try {
          const storageValue = localStorage.getItem("nowboarding.scale");
          if (storageValue) {
            nbrange.value = storageValue;
            nbrange.dispatchEvent(new Event("input"));
          }
        } catch (e) {
          // Local storage unavailable
        }
      }

      const complaintTextEl = document.getElementById("nbcommon-complaint");
      complaintTextEl.textContent = `${this.gamedatas.complaint}/3`;
      const hourEl = commonEl.querySelector(".hour");
      const hourIconEl = hourEl.querySelector(".icon");
      const hourTextEl = hourEl.querySelector("span");
      this.swapClass(hourIconEl, "hour-", `hour-${this.gamedatas.hour.hour}`);
      let hourTxt = _(this.gamedatas.hour.hourDesc);
      if (this.gamedatas.hour.round) {
        hourTxt += ` (${this.gamedatas.hour.round}/${this.gamedatas.hour.total})`;
      }
      hourTextEl.textContent = hourTxt;
      if (this.gamedatas.vip) {
        const vipTextEl = document.getElementById("nbcommon-vip");
        vipTextEl.textContent = this.gamedatas.hour.vipRemain || "0";
      }
    },

    createWeather(location, token) {
      console.log(`🌤️ Create weather ${token} at ${location}`);
      this.mapEl.insertAdjacentHTML("beforeend", `<div id="weather-${location}" class="weather node node-${location}"><i class="icon weather-${token}"></i></div>`);
    },

    deleteWeather() {
      console.log(`❌ Delete weather`);
      document.querySelectorAll("#nbmap .weather").forEach((el) => el.remove());
    },

    renderPlane(plane) {
      let planeEl = document.getElementById(`plane-${plane.id}`);
      if (plane.location && !planeEl) {
        // Create plane
        console.log(`✈️ Create plane ${plane.id} at ${plane.location}`);
        const cssClass = plane.id == this.player_id ? "mine" : "";
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="plane-${plane.id}" class="plane node node-${plane.location} ${cssClass}"><div id="planeicon-${plane.id}" class="icon plane-${plane.alliances[0]}"></div></div>`);
        const rotation = this.getRotation(plane);
        if (rotation) {
          const iconEl = document.getElementById(`planeicon-${plane.id}`);
          iconEl.style.transform = `rotate(${rotation}deg)`;
        }
        // Update panel
        this.swapClass(`overall_player_board_${plane.id}`, "panel-", `panel-${plane.alliances[0]}`);
      } else if (!plane.location && planeEl) {
        // Delete plane
        console.log(`❌ Delete plane ${plane.id}`);
        this.getElement(`plane-${plane.id}`)?.remove();
        // Update panel
        this.swapClass(`overall_player_board_${plane.id}`, "panel-");
      }
    },

    movePlane(plane) {
      console.log(`✈️ Move plane ${plane.id} to ${plane.location}`);
      const rotation = this.getRotation(plane);
      document.querySelector(`#plane-${plane.id} .icon`).style.transform = `rotate(${rotation}deg)`;
      this.swapClass(`plane-${plane.id}`, "node-", `node-${plane.location}`);
    },

    renderPlaneGauges(plane) {
      let alliancesEl = document.getElementById(`alliances-${plane.id}`);
      if (alliancesEl == null) {
        const parentEl = document.getElementById(`player_board_${plane.id}`);
        parentEl.insertAdjacentHTML("beforeend", `<div id="alliances-${plane.id}" class="gauges"></div>`);
        alliancesEl = document.getElementById(`alliances-${plane.id}`);
      }

      // Update alliances tag
      if (alliancesEl.childNodes.length != plane.alliances.length) {
        alliancesEl.textContent = "";
        for (const alliance of plane.alliances) {
          alliancesEl.insertAdjacentHTML("beforeend", `<div class="nbtag alliance alliance-${alliance}" data-alliance="${alliance}"><i class="icon logo-${alliance}"></i> ${alliance}</div>`);
        }
      }

      let gaugesEl = document.getElementById(`gauges-${plane.id}`);
      if (gaugesEl == null) {
        const parentEl = document.getElementById(`player_board_${plane.id}`);
        parentEl.insertAdjacentHTML(
          "beforeend",
          `<div id="gauges-${plane.id}" class="gauges">
  <div class="nbsection">
    <div class="nblabel">${_("Cash")}</div>
    <div class="nbtag cash"><i class="icon cash"></i> <span id="gauge-cash-${plane.id}"></span></div>
  </div>
  <div class="nbsection">
    <div class="nblabel">${_("Seats")}</div>
    <div class="nbtag seat"><i class="icon seat"></i> <span id="gauge-seat-${plane.id}"></span></div>
  </div>
  <div class="nbsection">
    <div class="nblabel">${_("Speed")}</div>
    <div class="nbtag speed"><i class="icon speed"></i> <span id="gauge-speed-${plane.id}"></span></div>
  </div>
</div>`
        );
        gaugesEl = document.getElementById(`gauges-${plane.id}`);
      }

      // Update cash tag
      const cashEl = document.getElementById(`gauge-cash-${plane.id}`);
      cashEl.textContent = plane.cashRemain;

      // Update seat tag
      const seatEl = document.getElementById(`gauge-seat-${plane.id}`);
      seatEl.textContent = `${Math.max(0, plane.seatRemain)}/${plane.seat}`;

      // Update speed tag
      const speedEl = document.getElementById(`gauge-speed-${plane.id}`);
      speedEl.textContent = `${Math.max(0, plane.speedRemain)}/${plane.speed}`;

      // Add/remove temp seat tag
      const tempSeatEl = document.getElementById(`gauge-temp-seat-${plane.id}`);
      if (plane.tempSeat && !tempSeatEl) {
        gaugesEl.insertAdjacentHTML("beforeend", `<div class="nbtag seat" id="gauge-temp-seat-${plane.id}"><i class="icon seat"></i> ${_("Temporary Seat")}</div>`);
      } else if (!plane.tempSeat && tempSeatEl) {
        tempSeatEl.remove();
      }

      // Add/remove temp speed tag
      const tempSpeedEl = document.getElementById(`gauge-temp-speed-${plane.id}`);
      if (plane.tempSpeed && !tempSpeedEl) {
        gaugesEl.insertAdjacentHTML("beforeend", `<div class="nbtag speed" id="gauge-temp-speed-${plane.id}"><i class="icon speed"></i> ${_("Temporary Speed")}</div>`);
      } else if (!plane.tempSpeed && tempSpeedEl) {
        tempSpeedEl.remove();
      }
    },

    renderPlaneManifest(plane) {
      let listEl = document.getElementById(`paxlist-${plane.id}`);
      if (listEl == null) {
        const parentEl = document.getElementById(`player_board_${plane.id}`);
        parentEl.insertAdjacentHTML(
          "beforeend",
          `<div id="manifest-${plane.id}" class="manifest">
  <div id="paxlist-${plane.id}" class="paxlist"></div>
</div>`
        );
        listEl = document.getElementById(`paxlist-${plane.id}`);
      }

      // Determine how many empty seats exist and belong
      const remain = Math.max(plane.seatRemain, 0);
      let emptyEls = listEl.querySelectorAll(".emptyseat:not(.temp)");

      // Add empty seats
      for (let i = emptyEls.length; i < remain; i++) {
        console.log("adding empty seat", i);
        listEl.insertAdjacentHTML("beforeend", `<div class="emptyseat"><i class="icon seat"></i> ${_("Empty Seat")}</div>`);
      }

      // Remove empty seats
      for (let i = remain; i < emptyEls.length; i++) {
        console.log("removing empty seat", i);
        emptyEls[i].remove();
      }

      // Add/remove the temp seat
      const tempEl = listEl.querySelector(".emptyseat.temp");
      console.log("temp seat", "has:", tempEl != null, "want:", plane.tempSeat);
      if (plane.tempSeat && !tempEl) {
        console.log(`add temp seat to plane ${plane.id}`);
        listEl.insertAdjacentHTML("beforeend", `<div class="emptyseat temp"><i class="icon seat"></i> ${_("Temporary Seat")}</div>`);
      } else if (!plane.tempSeat && tempEl) {
        console.log(`remove temp seat from plane ${plane.id}`);
        tempEl.remove();
      }
    },

    renderMapManifest(manifestId, parentEl) {
      parentEl = this.getElement(parentEl);
      parentEl.insertAdjacentHTML(
        "beforeend",
        `<div id="manifest-${manifestId}" class="manifest">
  <div class="location">${manifestId}</div>
  <div id="paxlist-${manifestId}" class="paxlist"></div>
</div>`
      );
      const manifestEl = document.getElementById(`manifest-${manifestId}`);
      manifestEl.addEventListener("mouseenter", (ev) => {
        this.onEnterMapManifest(manifestId);
      });
      manifestEl.addEventListener("mouseleave", (ev) => {
        this.onLeaveMapManifest(manifestId);
      });
    },

    renderMapNode(node) {
      this.mapEl.insertAdjacentHTML("beforeend", `<div id="node-${node}" class="marker node node-${node}"></div>`);
      if (node.length == 3) {
        const nodeEl = document.getElementById(`node-${node}`);
        nodeEl.addEventListener("mouseenter", (ev) => {
          this.onEnterMapManifest(node);
        });
        nodeEl.addEventListener("mouseleave", (ev) => {
          this.onLeaveMapManifest(node);
        });
      }
    },

    onEnterMapManifest(manifestId) {
      const manifestEl = document.getElementById(`manifest-${manifestId}`);
      manifestEl.classList.add("is-active");
      const markerEl = document.querySelector(`#nbmap .marker.node.node-${manifestId}`);
      markerEl.classList.add("is-active", "pinging");
    },

    onLeaveMapManifest(manifestId) {
      const manifestEl = document.getElementById(`manifest-${manifestId}`);
      manifestEl.classList.remove("is-active");
      const markerEl = document.querySelector(`#nbmap .marker.node.node-${manifestId}`);
      markerEl.classList.remove("is-active", "pinging");
    },

    async renderPax(pax) {
      console.log(`🧑 Render pax ${pax.id}: location=${pax.location}, playerId=${pax.playerId}, status=${pax.status}`);

      // Determine where the pax is and where it belongs
      let paxEl = document.getElementById(`pax-${pax.id}`);
      let listEl = null;
      if (pax.status == "SECRET" || pax.status == "PORT") {
        // Belongs in an airport
        listEl = document.getElementById(`paxlist-${pax.location}`);
      } else if (pax.status == "SEAT") {
        // Belongs in a plane
        listEl = document.getElementById(`paxlist-${pax.playerId}`);
      }

      if (!listEl) {
        // Pax shouldn't exist
        if (paxEl && pax.id > 0) {
          this.deletePax(pax);
        }
        return;
      }

      if (!paxEl) {
        // Pax doesn't exist but should
        // Create new pax
        listEl.insertAdjacentHTML(
          "beforeend",
          `<div id="pax-${pax.id}" class="pax">
  <div class="anger">
    <div class="icon"></div>
    <div class="count"></div>
  </div>
  <div class="vip"></div>
  <div class="ticket">
    <span class="origin"></span>
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
      const originEl = paxEl.querySelector(".origin");
      const destinationEl = paxEl.querySelector(".destination");
      const cashEl = paxEl.querySelector(".cash");

      if (pax.status == "SECRET") {
        paxEl.style.order = 500 + pax.id;
        paxEl.classList.add("is-secret");
        angerIconEl.classList.add("question");
        angerCountEl.textContent = "";
        originEl.textContent = _("WELCOME ABOARD");
      } else {
        paxEl.style.order = (4 - pax.anger) * 100 + Math.abs(pax.id);
        paxEl.classList.remove("is-secret");
        cashEl.textContent = `$${pax.cash}`;
        originEl.textContent = `${pax.origin} » `;
        destinationEl.textContent = pax.destination;
        this.swapClass(angerEl, "anger-", `anger-${pax.anger}`);
        this.swapClass(angerIconEl, ["question", "anger-"], `anger-${pax.anger}`);
        angerCountEl.textContent = pax.anger;
      }
      if (pax.vip) {
        paxEl.classList.add("is-vip");
        paxEl.title = _(pax.vip.name) + ": " + _(pax.vip.desc);
        vipEl.innerHTML = '<i class="icon vipstar"></i>' + _(pax.vip.name);
      }

      // Move the pax (if necessary)
      // Don't move VIP Double until the original moves
      if (pax.id > 0) {
        this.movePax(paxEl, listEl);
        // VIP Double is handled concurrent with original
        const double = this.gamedatas.pax[-1 * pax.id];
        if (double) {
          const doubleEl = document.getElementById(`pax-${double.id}`);
          if (doubleEl) {
            doubleEl.style.position = null;
            doubleEl.style.top = null;
            doubleEl.style.left = null;
            await this.movePax(doubleEl, listEl);
            if (double.status == "DELETED") {
              this.deletePax(double);
            }
          }
        }
      }
    },

    async movePax(paxEl, listEl) {
      if (paxEl.parentElement == listEl) {
        // Already correct, nothing to do
        return;
      }

      // Record start position
      const start = paxEl.getBoundingClientRect();

      // Make a copy and put it at start
      const cloneEl = paxEl.cloneNode(true);
      cloneEl.id += "-clone";
      this.positionElement(cloneEl, start);
      document.body.appendChild(cloneEl);

      // Move and record end position
      listEl.appendChild(paxEl);
      const end = paxEl.getBoundingClientRect();
      paxEl.style.opacity = "0";

      // Animate the copy to the end
      await this.translateElement(cloneEl, start, end);
      paxEl.style.opacity = null;
      cloneEl.remove();
    },

    deletePax(pax) {
      console.log(`❌ Delete passenger ${pax.id} (${pax.status})`);
      const paxEl = document.getElementById(`pax-${pax.id}`);
      if (!paxEl) {
        console.warn("Delete a passenger which did not exist???");
        return;
      }

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
      cloneEl.style.position = "absolute";
      cloneEl.style.top = `${start.top + window.scrollY}px`;
      cloneEl.style.left = `${start.left + window.scrollX}px`;
      document.body.appendChild(cloneEl);

      // Delete the pax
      paxEl.remove();

      // Animate the copy to the end
      setTimeout(() => {
        cloneEl.addEventListener("transitionend", () => {
          cloneEl.remove();
        });
        cloneEl.style.transform = `translate(${end.x - start.x}px, ${end.y - start.y}px)`;
      }, 10);
    },

    renderWarnings() {
      const airports = ["ATL", "DEN", "DFW", "JFK", "LAX", "MIA", "ORD", "SEA", "SFO"];
      let angerAirports = {};
      for (const paxId in this.gamedatas.pax) {
        const pax = this.gamedatas.pax[paxId];
        if (pax.status == "PORT" && pax.anger == 3) {
          angerAirports[pax.location] = true;
        }
      }
      console.log("angerAirports", angerAirports);
      for (const airport of airports) {
        const manifestEl = document.getElementById(`manifest-${airport}`);
        if (manifestEl) {
          manifestEl.classList.toggle("is-anger", angerAirports[airport] == true);
        }
        const markerEl = document.querySelector(`#nbmap .marker.node.node-${airport}`);
        if (markerEl) {
          markerEl.classList.toggle("is-anger", angerAirports[airport] == true);
          markerEl.classList.toggle("pinging-anger", angerAirports[airport] == true);
        }
      }
    },

    renderBuys() {
      let buysEl = document.getElementById("nbbuys");
      if (!buysEl) {
        const parentEl = document.getElementById("generalactions");
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
          cssClass = `alliance-${buy.alliance}`;
          icon = `<div class="icon alliance logo-${buy.alliance}"></div>`;
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
          buyEl.addEventListener("click", (ev) => {
            this.takeAction("buy", buy);
          });
        }
        if (buy.type == "ALLIANCE") {
          buyEl.addEventListener("mouseenter", (ev) => {
            this.onEnterMapManifest(buy.alliance);
          });
          buyEl.addEventListener("mouseleave", (ev) => {
            this.onLeaveMapManifest(buy.alliance);
          });
        }
      }
    },

    renderLedger(args) {
      const walletHtml = args.wallet.map((cash) => `<div class="nbtag cash"><i class="icon cash"></i> ${cash}</div>`).join("");
      const walletSum = args.wallet.reduce((a, b) => a + b, 0);
      let ledgerHtml = "";
      if (args.ledger?.length > 0) {
        for (const l of args.ledger) {
          console.log("l", l);
          let txt = l.type;
          if (l.type == "ALLIANCE") {
            txt = this.format_string_recursive(_("Purchase alliance ${alliance}"), { alliance: l.arg });
          } else if (l.type == "SEAT") {
            txt = this.format_string_recursive(_("Purchase seat ${seat}"), { seat: l.arg });
          } else if (l.type == "SPEED") {
            txt = this.format_string_recursive(_("Purchase speed ${speed}"), { speed: l.arg });
          } else if (l.type == "TEMP_SEAT") {
            txt = this.format_string_recursive(_("Purchase ${temp}"), { temp: _("Temporary Seat"), tempIcon: "seat" });
          } else if (l.type == "TEMP_SPEED") {
            txt = this.format_string_recursive(_("Purchase ${temp}"), { temp: _("Temporary Speed"), tempIcon: "speed" });
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
      const alliance = this.gamedatas.planes[this.player_id].alliances[0];
      let moveCount = 0;
      for (const i in moves) {
        const move = moves[i];
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="move-${move.location}" class="move node node-${move.location} gradient-${alliance}">${move.fuel}</div>`);
        const moveEl = document.getElementById(`move-${move.location}`);
        moveEl.addEventListener("click", (e) => {
          this.takeAction("move", move);
        });
        moveCount++;
      }
      console.log(`🗺️ Add ${moveCount} possible moves`);
    },

    deleteMoves() {
      console.log(`❌ Delete possible moves`);
      document.querySelectorAll("#nbmap .move").forEach((el) => el.remove());
    },
  });
});
