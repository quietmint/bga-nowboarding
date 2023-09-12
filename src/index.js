define(["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter"], function (dojo, declare) {
  const playSoundSuper = window.playSound;
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
      this.scaleEl = document.getElementById("nbscale");
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
          this.renderPlaneManifest(plane);
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
          this.renderPlaneManifest(plane);
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
      dojo.subscribe("planes", this, "onNotify");
      dojo.subscribe("sound", this, "onNotify");
      dojo.subscribe("weather", this, "onNotify");
      this.notifqueue.setSynchronous("complaint", 2000);
      this.notifqueue.setSynchronous("flyTimer", 5500);
      this.notifqueue.setSynchronous("weather", 2000);

      // Setup preferences
      this.setupPrefs();

      // Production bug report handler
      dojo.subscribe("loadBug", this, function loadBug(n) {
        function fetchNextUrl() {
          const url = n.args.urls.shift();
          console.log("Fetching URL", url);
          dojo.xhrGet({
            url: url + "&request_token=" + bgaConfig.requestToken,
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
      if (log && args) {
        // Translate log message
        log = this.clienttranslate_string(log) || "";

        // Translate args listed in i18n
        if (args.i18n) {
          for (const k of args.i18n) {
            args[k] = this.clienttranslate_string(args[k]) || "";
          }
        }

        // Format args with HTML
        for (const k in args) {
          if (k == "i18n") {
            continue;
          }

          // Process nested objects recursively
          if (args[k]?.log && args[k]?.args) {
            args[k] = this.format_string_recursive(args[k].log, args[k].args);
          }

          if (k == "alliance") {
            args[k] = `<span class="nbtag alliance-${args[k]}"><i class="icon logo-${args[k]}"></i> ${args[k]}</span>`;
          } else if (k == "cash") {
            args[k] = `<span class="nbtag cash"><i class="icon cash"></i> ${args[k]}</span>`;
          } else if (k == "complaint") {
            args[k] = `<span class="nbtag complaint"><i class="icon complaint"></i> ${args[k]}</span>`;
          } else if (k == "location") {
            args[k] = `<b>${args[k]}</b>`;
          } else if (k == "seat") {
            args[k] = `<span class="nbtag seat"><i class="icon seat"></i> ${args[k]}</span>`;
          } else if (k == "speed") {
            args[k] = `<span class="nbtag speed"><i class="icon speed"></i> ${args[k]}</span>`;
          } else if (k == "temp") {
            args[k] = `<span class="nbtag ${args.tempIcon}"><i class="icon ${args.tempIcon}"></i> ${args[k]}</span>`;
          } else if (k == "vip") {
            args[k] = `<span class="nbtag vip"><i class="icon vipstar"></i> ${args[k]}</span>`;
          } else if (k == "wrapper") {
            if (args[k] == "weatherFlex") {
              const weatherIcon = `<div class="weather node"><i class="icon weather-${args.weatherIcon}"></i>` + (args.weatherIcon == "SLOW" ? '<div class="fuel">2</div>' : "") + "</div>";
              log = `<div class="log-flex-wrapper">${weatherIcon}<div>${log}</div></div>`;
            }
          }
        }

        // Finally apply string substitution
        return dojo.string.substitute(log, args);
      }
      return "";
    },

    /* @Override */
    getRanking: function () {
      this.inherited(arguments);
      this.pageheaderfooter.showSectionFromButton("pageheader_howtoplay");
      this.onShowGameHelp();
    },

    /* @Override */
    onScreenWidthChange() {
      // Remove broken "zoom" property added by BGA framework
      this.gameinterface_zoomFactor = 1;
      dojo.style("page-content", "zoom", "");
      dojo.style("page-title", "zoom", "");
      dojo.style("right-side-first-part", "zoom", "");
      this.computeViewport();
      this.resizeMap();
    },

    computeViewport() {
      // Force device-width during chat
      let chatVisible = false;
      for (const w in this.chatbarWindows) {
        if (this.chatbarWindows[w].status == "expanded") {
          chatVisible = true;
          break;
        }
      }

      const width = chatVisible ? "device-width" : 980;
      this.interface_min_width = width;
      this.default_viewport = "width=" + width;
      return this.default_viewport;
    },

    /* @Override */
    expandChatWindow() {
      this.inherited(arguments);
      dojo.query('meta[name="viewport"]')[0].content = this.computeViewport();
    },

    /* @Override */
    collapseChatWindow() {
      this.inherited(arguments);
      dojo.query('meta[name="viewport"]')[0].content = this.computeViewport();
    },

    /* @Override */
    onPlaceLogOnChannel(notif) {
      const chatId = this.next_log_id;
      const result = this.inherited(arguments);
      if (result && chatId != this.next_log_id && this.gamedatas.planes && (notif.type == "chatmessage" || notif.type == "tablechat") && notif.args?.player_id) {
        const chatEl = document.getElementById(`dockedlog_${chatId}`);
        const chatPlane = this.gamedatas.planes[notif.args.player_id];
        if (chatEl && chatPlane?.alliances?.length) {
          // Color chat messages by plane alliance
          chatEl.classList.add(`chatlog-${chatPlane.alliances[0]}`);
        }
      }
      return result;
    },

    /* @Override */
    adaptPlayersPanels() {
      // do nothing
    },

    /* @Override */
    updateReflexionTime() {
      this.inherited(arguments);
      if (this.gamedatas.gamestate.name == "fly" && this.gamedatas.gamestate.args.endTime) {
        const seconds = this.gamedatas.gamestate.args.endTime - Date.now() / 1000;
        if (!this.gamedatas.gamestate.args.sound && seconds <= 7) {
          // Play the clock sound only once, at 7 seconds
          playSoundSuper("time_alarm");
          this.gamedatas.gamestate.args.sound = true;
        }
        const countdownEl = document.getElementById("nbcountdown");
        if (countdownEl) {
          countdownEl.textContent = this.formatReflexionTime(seconds).string;
        }
      }
    },

    playSound(sound) {
      if (this.gamedatas.gamestate.name == "fly" && this.gamedatas.gamestate.args.endTime && sound == "time_alarm") {
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

    // ----------------------------------------------------------------------

    onEnteringState(stateName, args) {
      if (stateName == "fly") {
        this.stabilizerOn();
        if (args.args.endTime) {
          // Add action bar countdown
          document.body.classList.add("no_time_limit");
          const destEl = document.getElementById("page-title");
          destEl.insertAdjacentHTML("afterbegin", '<div id="nbcountdown"></div>');
          // Start timer
          if (flyTimer) {
            window.clearTimeout(flyTimer);
          }
          const millis = Math.max(0, args.args.endTime * 1000 - Date.now()) + Math.random() * 1000;
          console.log("⌚ Timer start", millis);
          flyTimer = window.setTimeout(() => {
            this.takeAction("flyTimer", { lock: false });
          }, millis);
        }
      } else if (stateName == "maintenance") {
        // Stop timer
        if (flyTimer) {
          window.clearTimeout(flyTimer);
          flyTimer = null;
        }
        // Remove action bar countdown
        document.getElementById("nbcountdown")?.remove();
        if (!this.gamedatas.noTimeLimit) {
          document.body.classList.remove("no_time_limit");
        }
      } else if (stateName == "prepare" || stateName == "gameEnd") {
        this.stabilizerOff();
      }
    },

    onUpdateActionButtons(stateName, args) {
      console.log(`▶️ State ${stateName}`, args);
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
              if (this.gamedatas.vip && this.gamedatas.hour.vipNeed) {
                const roundRemain = this.gamedatas.hour.total - this.gamedatas.hour.round + 1;
                dialog = this.format_string_recursive(_("You did not accept a VIP, but ${vipRemain} VIPs remain and ${hourDesc} ends in ${roundRemain} rounds"), {
                  hourDesc: this.gamedatas.hour.hourDesc,
                  roundRemain,
                  vipRemain: this.gamedatas.hour.vipRemain,
                });
              }
              if (dialog) {
                this.confirmationDialog(dialog, () => {
                  this.takeAction("prepareDone");
                });
              } else {
                this.takeAction("prepareDone");
              }
            });
            if (this.gamedatas.vip) {
              if (this.gamedatas.hour.vipNew) {
                const txt = this.format_string_recursive(_("Decline VIP (${vipRemain} remaining)"), { vipRemain: this.gamedatas.hour.vipRemain });
                this.addActionButton("button_vip", txt, () => this.takeVipAction());
              } else if (this.gamedatas.hour.vipRemain) {
                const txt = this.format_string_recursive(_("Accept VIP (${vipRemain} remaining)"), { vipRemain: this.gamedatas.hour.vipRemain });
                this.addActionButton("button_vip", txt, () => this.takeVipAction(), null, false, this.gamedatas.hour.vipNeed ? "red" : "blue");
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
          const vipEl = document.getElementById("button_vip");
          if (vipEl) {
            vipEl.textContent = this.format_string_recursive(this.gamedatas.hour.vipNew ? _("Decline VIP (${vipRemain} remaining)") : _("Accept VIP (${vipRemain} remaining)"), { vipRemain: this.gamedatas.hour.vipRemain });
            vipEl.classList.toggle("bgabutton_blue", !this.gamedatas.hour.vipNeed);
            vipEl.classList.toggle("bgabutton_red", this.gamedatas.hour.vipNeed);
          }
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
        if (sound) {
          playSound("nowboarding_cash");
        }
        this.renderMapCounts();
      } else if (notif.type == "planes") {
        for (const plane of notif.args.planes) {
          this.gamedatas.planes[plane.id] = plane;
          this.renderPlane(plane);
          this.renderPlaneGauges(plane);
          if (this.gamedatas.gamestate.name != "fly") {
            this.renderPlaneManifest(plane);
          }
        }
      } else if (notif.type == "sound") {
        suppressSounds = notif.args.suppress;
        playSound(`nowboarding_${notif.args.sound}`);
      } else if (notif.type == "weather") {
        suppressSounds = ["yourturn"];
        playSound("nowboarding_plane");
        this.gamedatas.map.weather = notif.args.weather;
        this.renderWeather();
      }
    },

    onPrefChange(id, value) {
      console.log("Preference changed", id, value);
      if (id == 150) {
        document.body.classList.toggle("no-animation", value == 2);
        document.body.classList.toggle("no-pinging", value > 0);
      }
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
              console.error(`Take action ${action} error in ${duration}ms`);
              reject();
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

    getRotationPlane(plane) {
      if (!plane.location || !plane.origin) {
        return null;
      }
      return this.getRotation(`node-${plane.location}`, `node-${plane.origin}`);
    },

    getRotation(el, otherEl) {
      const elPos = dojo.position(el);
      const otherPos = dojo.position(otherEl);
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
        const fallback = setTimeout(resolve, 1100);
        el.addEventListener("transitionend", () => {
          resolve();
          clearTimeout(fallback);
        });
        el.offsetHeight; // repaint
        startFn(el);
      });
    },

    translateElement(el, start, end) {
      return this.transitionElement(el, (el) => (el.style.transform = `translate(${end.x - start.x}px, ${end.y - start.y}px)`));
    },

    stabilizerOn() {
      console.log("🔒 Stabilizer on");
      // Lock map manifest height
      // const topLists = document.querySelectorAll("#manifests-top .paxlist");
      // const heights = Array.from(topLists, (listEl) => listEl.clientHeight - 10);
      // const maxHeight = Math.max(...heights);
      // topLists.forEach((listEl) => (listEl.style.height = `${maxHeight}px`));
    },

    stabilizerOff() {
      console.log("🔓 Stabilizer off");
      // Unlock map manifest height
      // document.querySelectorAll("#manifests-top .paxlist").forEach((listEl) => (listEl.style.height = null));
      // Remove gaps in map manifest
      document.querySelectorAll(".paxlist.is-map .paxslot.is-empty").forEach((slotEl) => slotEl.remove());
      this.sortManifests();
    },

    sortManifests() {
      const paxOrder = [];
      Object.values(this.gamedatas.pax).forEach(function (pax) {
        const paxEl = document.getElementById(`pax-${pax.id}`);
        if (paxEl) {
          // Sort by secret, anger DESC, destination, cash DESC, ID
          const order = (pax.status == "SECRET" ? 0 : pax.status != "SEAT" ? 5 - pax.anger + "." : "") + pax.destination + "." + (5 - pax.cash) + "." + String(pax.id).padStart(2, "0");
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
        <div class="nbtag"><i class="icon vipstar"></i> <span id="nbcommon-vip"></span></div>
      </div>`
          : "";
        const commonHtml = `<div id="nbcommon">
  <div class="nbsection">
    <div class="nblabel">${_("Complaints")}</div>
    <div class="nbtag"><i class="icon complaint"></i> <span id="nbcommon-complaint"></span></div>
  </div>
  <div class="nbsection">
    <div class="nblabel">${_("Round")}</div>
    <div class="nbtag hour"><i class="icon"></i> <span></span></div>
  </div>
  ${vipHtml}
  <div class="nbsection">
    <div class="nblabel">${_("Map Size")}</div>
    <div class="nbtag"><input type="range" id="nbrange" min="40" max="100" step="2" value="100"></div>
  </div>
</div>`;
        const parentEl = document.getElementById("player_boards");
        parentEl.insertAdjacentHTML("beforebegin", commonHtml);
        commonEl = document.getElementById("nbcommon");

        const nbrange = document.getElementById("nbrange");
        nbrange.addEventListener("input", (ev) => {
          const scaleEl = document.getElementById("nbscale");
          if (scaleEl) {
            scaleEl.style.width = `${nbrange.value}%`;
            this.resizeMap();
            try {
              localStorage.setItem("nowboarding.scale", nbrange.value);
            } catch (e) {
              // Local storage unavailable
            }
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
      complaintTextEl.innerHTML = `<span class="${this.gamedatas.complaint > 0 ? "bb" : ""}">${this.gamedatas.complaint}</span>/3`;
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
      }
    },

    createWeather(location, token) {
      console.log(`🌤️ Create weather ${token} at ${location}`);
      const fuel = token == "FAST" ? "" : '<div class="fuel">2</div>';
      const txt = token == "FAST" ? _("Tailwind: No fuel cost to move through this space") : _("Storm: Double fuel cost to move through this space");
      const alliance = this.gamedatas.map.nodes[location];
      let specialHtml = "";
      if (alliance) {
        const specialTxt = this.format_string_recursive(_("Special Route: Restricted to alliance ${specialRoute}"), { specialRoute: alliance });
        specialHtml = `<div class="specialtag alliance-${alliance}" title="${specialTxt}"><i class="icon logo-${alliance}"></i></div>`;
      }
      this.mapEl.insertAdjacentHTML("beforeend", `<div id="weather-${location}" class="weather node node-${location}" title="${txt}" style="opacity: 0">${specialHtml}<i class="icon weather-${token}"></i>${fuel}</div>`);
      const el = document.getElementById(`weather-${location}`);
      this.transitionElement(el, (el) => (el.style.opacity = null));
    },

    renderPlane(plane) {
      let planeEl = document.getElementById(`plane-${plane.id}`);
      if (plane.location && !planeEl) {
        // Create plane
        console.log(`✈️ Create plane ${plane.id} at ${plane.location}`);
        const cssClass = plane.id == this.player_id ? "mine" : "";
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="plane-${plane.id}" class="plane node node-${plane.location} ${cssClass}" title="${this.gamedatas.players[plane.id].name}"><div id="planeicon-${plane.id}" class="icon plane-${plane.alliances[0]}"></div></div>`);
        const rotation = this.getRotationPlane(plane);
        if (rotation) {
          const iconEl = document.getElementById(`planeicon-${plane.id}`);
          iconEl.style.transform = `rotate(${rotation}deg)`;
        }
        // Update panel
        this.swapClass(`overall_player_board_${plane.id}`, "panel-", `panel-${plane.alliances[0]}`);
      } else if (!plane.location && planeEl) {
        // Delete plane
        console.log(`❌ Delete plane ${plane.id}`);
        planeEl.remove();
        // Update panel
        this.swapClass(`overall_player_board_${plane.id}`, "panel-");
      }
    },

    movePlane(plane) {
      console.log(`✈️ Move plane ${plane.id} to ${plane.location}`);
      const rotation = this.getRotationPlane(plane);
      document.querySelector(`#plane-${plane.id} .icon`).style.transform = `rotate(${rotation}deg)`;
      this.swapClass(`plane-${plane.id}`, "node-", `node-${plane.location}`);
    },

    renderPlaneGauges(plane) {
      let boardEl = document.getElementById(`board-${plane.id}`);
      if (boardEl == null) {
        const scoreEl = document.getElementById(`player_score_${plane.id}`);
        scoreEl.insertAdjacentHTML("beforebegin", `<i id="gps-${plane.id}" class="icon gps" title="${_("Current Position")}"></i>`);
        const gpsEl = document.getElementById(`gps-${plane.id}`);
        gpsEl.addEventListener("click", (ev) => {
          this.onEnterGps(plane.id);
        });

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
          alliancesEl.insertAdjacentHTML("beforeend", `<div class="nbtag alliance alliance-${alliance}" data-alliance="${alliance}"><i class="icon logo-${alliance}"></i> ${alliance}</div>`);
        }
      }

      // Update speed tag
      const speedEl = document.getElementById(`gauge-speed-${plane.id}`);
      const speedRemain = Math.max(0, plane.speedRemain) + (plane.tempSpeed ? 1 : 0);
      speedEl.innerHTML = `<span class="${speedRemain > 0 ? "bb" : ""}">${speedRemain}</span>/${plane.speed}`;

      // Update seat tag
      const seatEl = document.getElementById(`gauge-seat-${plane.id}`);
      seatEl.innerHTML = `${plane.seat}`;

      // Update cash tag
      const cashEl = document.getElementById(`gauge-cash-${plane.id}`);
      cashEl.textContent = plane.cashRemain;

      // Add/remove temp speed tag
      const tempSpeedEl = document.getElementById(`gauge-temp-speed-${plane.id}`);
      if (plane.tempSpeed && !tempSpeedEl) {
        gaugesEl.insertAdjacentHTML("beforeend", `<div class="nbtag speed" id="gauge-temp-speed-${plane.id}" title="${_("Temporary Speed")}"><i class="icon speed"></i> <span class="ss">${_("Temp Speed")}</span></div>`);
      } else if (!plane.tempSpeed && tempSpeedEl) {
        tempSpeedEl.remove();
      }

      // Add/remove temp seat tag
      const tempSeatEl = document.getElementById(`gauge-temp-seat-${plane.id}`);
      if (plane.tempSeat && !tempSeatEl) {
        gaugesEl.insertAdjacentHTML("beforeend", `<div class="nbtag seat" id="gauge-temp-seat-${plane.id}" title="${_("Temporary Seat")}"><i class="icon seat"></i> <span class="ss">${_("Temp Seat")}</span></div>`);
      } else if (!plane.tempSeat && tempSeatEl) {
        tempSeatEl.remove();
      }
    },

    onEnterGps(planeId) {
      const location = this.gamedatas.planes[planeId]?.location;
      if (location) {
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="spotlight-plane" class="spotlight node node-${location}"></div>`);
        const spotlightEl = document.getElementById(`spotlight-plane`);
        spotlightEl.addEventListener("click", (ev) => {
          this.onLeaveGps();
        });
        this.transitionElement(spotlightEl, (el) => (el.style.opacity = "1"));
      }
    },

    async onLeaveGps() {
      const spotlightEl = document.getElementById(`spotlight-plane`);
      if (spotlightEl) {
        await this.transitionElement(spotlightEl, (el) => (el.style.opacity = "0"));
        spotlightEl.remove();
      }
    },

    renderPlaneManifest(plane) {
      const listEl = document.getElementById(`paxlist-${plane.id}`);

      // Add empty seats (purchase during prepare)
      const emptyCount = Math.max(plane.seatRemain, 0) + (plane.tempSeat ? 1 : 0);
      const emptyEls = listEl.querySelectorAll(".paxslot.is-empty");
      for (let i = emptyEls.length; i < emptyCount; i++) {
        this.renderSlot(listEl);
      }

      // Remove empty seats (undo during prepare)
      for (let i = emptyCount; i < emptyEls.length; i++) {
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
      if (!document.body.classList.contains("mobile_version")) {
        const manifestEl = document.getElementById(`manifest-${manifestId}`);
        manifestEl.addEventListener("mouseenter", (ev) => {
          this.onEnterMapManifest(manifestId);
        });
        manifestEl.addEventListener("mouseleave", (ev) => {
          this.onLeaveMapManifest(manifestId);
        });
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
          `<div id="node-${node}" class="port node node-${node}">
  <div id="leadline-${node}" class="leadline"></div>
  <i class="icon people"></i><span id="nodecount-${node}">0</span>
</div>`
        );
        if (!document.body.classList.contains("mobile_version")) {
          const nodeEl = document.getElementById(`node-${node}`);
          nodeEl.addEventListener("mouseenter", (ev) => {
            this.onEnterMapManifest(node);
          });
          nodeEl.addEventListener("mouseleave", (ev) => {
            this.onLeaveMapManifest(node);
          });
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
        this.scaleEl.style.setProperty("--map-width", this.mapEl.clientWidth + "px");
        this.renderMapLeads();
      }
    },

    renderMapLeads() {
      const airports = ["ATL", "DEN", "DFW", "JFK", "LAX", "MIA", "ORD", "SEA", "SFO"];
      for (const airport of airports) {
        const leadEl = document.getElementById(`leadline-${airport}`);
        if (leadEl) {
          const manifestEl = document.getElementById(`paxlist-${airport}`);
          const manifestPos = manifestEl.getBoundingClientRect();
          const parentId = manifestEl.parentElement.parentElement.id;
          const nodeEl = document.getElementById(`node-${airport}`);
          const nodePos = nodeEl.getBoundingClientRect();
          const nodeLeftMid = nodePos.left + nodePos.width / 2;
          const isBetweenLeft = nodeLeftMid >= manifestPos.left && nodeLeftMid <= manifestPos.left + manifestPos.width;

          if (parentId == "manifests-top") {
            // straight lead up
            let height = nodePos.top - manifestPos.top - manifestPos.height - 8;
            leadEl.style.height = `${height}px`;
            leadEl.style.width = "0px";
            leadEl.style.top = null;
            leadEl.style.bottom = `${nodePos.height}px`;
            leadEl.style.left = null;
            leadEl.style.right = null;
          } else if (parentId == "manifests-bottom") {
            let height = manifestPos.top - nodePos.top - nodePos.height - 8;
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
                width = manifestPos.left + manifestPos.width * 0.15 - nodePos.left - nodePos.width - 8;
                leadEl.style.left = `${nodePos.width}px`;
                leadEl.style.right = null;
                leadEl.classList.toggle("left", false);
              } else {
                // curved to the left down (DFW)
                width = nodePos.left - manifestPos.left - manifestPos.width * 0.85 - 8;
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
            let height = manifestPos.top - nodePos.top - nodePos.height / 2 - 8;
            if (height > 4) {
              // curved lead right + down
              let height = manifestPos.top - nodePos.top - nodePos.height / 2 - 8;
              let width = manifestPos.left + manifestPos.width * 0.25 - nodePos.left - nodePos.width - 8;
              leadEl.style.height = `${height}px`;
              leadEl.style.width = `${width}px`;
              leadEl.style.top = `${nodePos.height / 2}px`;
              leadEl.style.bottom = null;
              leadEl.style.left = `${nodePos.width}px`;
              leadEl.style.right = null;
              leadEl.classList.toggle("curved", true);
            } else {
              // straight lead right
              let width = manifestPos.left - nodePos.left - nodePos.width - 8;
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
      const airports = {
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
      const pax = Object.values(this.gamedatas.pax).filter((x) => x.status == "PORT" || x.status == "SECRET");
      for (const airport in airports) {
        const paxCount = pax.filter((x) => x.location == airport).length;
        const isAngry = pax.some((x) => x.location == airport && x.anger == 3);
        const manifestEl = document.getElementById(`manifest-${airport}`);
        if (manifestEl) {
          manifestEl.classList.toggle("is-anger", isAngry);
          const emptyEl = manifestEl.querySelector(".emptytxt");
          emptyEl.textContent = paxCount == 0 ? this.format_string_recursive(_("No passengers in ${city}"), { city: airports[airport] }) : "";
        }
        const nodeEl = document.getElementById(`node-${airport}`);
        if (nodeEl) {
          nodeEl.classList.toggle("is-anger", isAngry);
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

      let plane = null;
      if (paxEl) {
        const planeId = paxEl.parentElement.parentElement.id.split("-").pop();
        plane = this.gamedatas.planes[planeId];
      }

      if (!destEl) {
        // Pax shouldn't exist
        if (paxEl) {
          this.deletePax(pax);
          if (plane && !plane.seatRemain) {
            this.deleteTempSeat(plane);
          }
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
      this.swapClass(angerIconEl, "anger-", `anger-${pax.anger}`);
      angerCountEl.textContent = pax.anger;
      if (pax.vipInfo) {
        paxEl.classList.add("is-vip");
        let vipName = _(pax.vipInfo.name);
        let vipDesc = _(pax.vipInfo.name) + ": " + _(pax.vipInfo.desc);
        if (pax.vipInfo.args) {
          vipName = this.format_string_recursive(vipName, pax.vipInfo.args);
          vipDesc = this.format_string_recursive(vipDesc, pax.vipInfo.args);
        }
        paxEl.title = vipDesc;
        vipEl.innerHTML = '<i class="icon vipstar"></i>' + vipName;
      } else {
        paxEl.classList.remove("is-vip");
        paxEl.title = null;
        vipEl.innerHTML = "";
      }

      // Move the pax (if necessary)
      this.movePax(paxEl, destEl);
      if (plane && !plane.seatRemain) {
        this.deleteTempSeat(plane);
      }
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
        console.warn("Delete a passenger which did not exist???");
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

    deleteTempSeat(plane) {
      console.log(`❌ Delete temporary seat for plane ${plane.id}`);
      document.querySelector(`#paxlist-${plane.id} .paxslot.is-empty`)?.remove();
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
          if (!document.body.classList.contains("mobile_version")) {
            buyEl.addEventListener("mouseenter", (ev) => {
              this.onEnterMapManifest(buy.alliance);
            });
            buyEl.addEventListener("mouseleave", (ev) => {
              this.onLeaveMapManifest(buy.alliance);
            });
          }
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
          specialHtml = `<div class="specialtag alliance-${move.alliance}" title="${specialTxt}"><i class="icon logo-${move.alliance}"></i></div>`;
          const weatherEl = document.getElementById(`weather-${move.location}`);
          if (weatherEl) {
            weatherEl.classList.add("hidespecial");
          }
        }
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="move-${move.location}" class="move node node-${move.location} gradient-${alliance}">${specialHtml}${move.fuel}</div>`);
        const moveEl = document.getElementById(`move-${move.location}`);
        moveEl.addEventListener("click", (e) => {
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
            } else if (pax.filter((x) => x.playerId == plane.id && x.status == "SEAT" && x.destination == plane.location).length > 0) {
              // Forgot to deliver
              dialog = this.format_string_recursive(_("You are leaving ${location} without delivering passengers"), {
                location: plane.location,
              });
            }
          }
          if (dialog) {
            this.confirmationDialog(dialog, () => {
              this.takeAction("move", move);
            });
          } else {
            this.takeAction("move", move);
          }
        });
        moveCount++;
      }
      console.log(`🗺️ Add ${moveCount} possible moves`);
    },

    deleteMoves() {
      console.log(`❌ Delete possible moves`);
      document.querySelectorAll("#nbmap .move").forEach((el) => el.remove());
      document.querySelectorAll(".weather.node.hidespecial").forEach((el) => el.classList.remove("hidespecial"));
    },
  });
});
