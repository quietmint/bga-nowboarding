define(["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter"], function (dojo, declare) {
  let lastErrorCode = null;
  return declare("bgagame.nowboarding", ebg.core.gamegui, {
    constructor() {
      dojo.place("loader_mask", "overall-content", "before");
    },

    setup(gamedatas) {
      console.log("🐣 Setup", gamedatas);

      // TODO!
      document.head.insertAdjacentHTML("beforeend", `<link rel="stylesheet" type="text/css" href="https://studio.boardgamearena.com:8084/data/themereleases/current/games/nowboarding/999999-9999/index.css?${Date.now()}"/>`);

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
      }

      // Setup notifications
      dojo.subscribe("buildPrimary", this, "onNotify");
      dojo.subscribe("complaint", this, "onNotify");
      dojo.subscribe("hour", this, "onNotify");
      dojo.subscribe("move", this, "onNotify");
      dojo.subscribe("pax", this, "onNotify");
      dojo.subscribe("planes", this, "onNotify");
      dojo.subscribe("reset", this, "onNotify");
      dojo.subscribe("sound", this, "onNotify");
      dojo.subscribe("weather", this, "onNotify");
      this.notifqueue.setSynchronous("complaint", 1000);
      this.notifqueue.setSynchronous("finale", 1000);
      this.notifqueue.setSynchronous("move", 1000);

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
          } else if (k == "seat") {
            args[k] = `<span class="nbtag seat"><i class="icon seat"></i> ${args[k]}</span>`;
          } else if (k == "speed") {
            args[k] = `<span class="nbtag speed"><i class="icon speed"></i> ${args[k]}</span>`;
          } else if (k == "temp") {
            args[k] = `<span class="nbtag ${args.tempIcon}"><i class="icon ${args.tempIcon}"></i> ${args[k]}</span>`;
          } else if (k == "complaint") {
            args[k] = `<span class="nbtag complaint"><i class="icon complaint"></i> ${args[k]}</span>`;
          } else if (k == "location" || k == "route" || k == "routeFast" || k == "routeSlow") {
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

    // ----------------------------------------------------------------------

    // onEnteringState(stateName, args) {},

    // onLeavingState(stateName) {},

    onUpdateActionButtons(stateName, args) {
      console.log(`▶️ State ${stateName}`, args);

      if (!this.is_spectator) {
        // Inactive players can undo
        if (stateName == "build" || stateName == "buildAlliance2" || stateName == "buildUpgrade" || stateName == "prepare") {
          this.addActionButton("button_reset", _("Start Over"), () => this.takeAction("reset"));
        }

        // Inactive players clear moves
        if (stateName == "fly" || stateName == "maintenance") {
          this.deleteMoves();
        }

        // Active players
        if (this.isCurrentPlayerActive()) {
          if (stateName == "preparePrivate") {
            this.addActionButton("button_flightBegin", _("Begin Round"), () => this.takeAction("flightBegin"));
            if (args.reset) {
              this.addActionButton("button_reset", _("Start Over"), () => this.takeAction("reset"));
            }
          } else if (stateName == "flyPrivate") {
            this.addActionButton("button_flightEnd", _("End Round"), () => this.takeAction("flightEnd"), null, false, "red");
            // Update the possible moves
            this.deleteMoves();
            if (args?.moves) {
              this.renderMoves(args.moves);
            }
          }

          if (args?.buys?.length > 0) {
            this.renderBuys(args.buys);
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
      } else if (notif.type == "complaint") {
        playSound("nowboarding_complaint");
        this.gamedatas.complaint = notif.args.total;
        this.renderCommon();
      } else if (notif.type == "hour") {
        this.gamedatas.hour = notif.args;
        if (this.gamedatas.hour.hour == "FINALE") {
          playSound("nowboarding_walkway");
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
          this.renderPax(pax);
        }
      } else if (notif.type == "planes") {
        for (const plane of notif.args.planes) {
          this.gamedatas.planes[plane.id] = plane;
          this.renderPlane(plane);
          this.renderPlaneGauges(plane);
          this.renderPlaneManifest(plane);
        }
      } else if (notif.type == "sound") {
        this.disableNextMoveSound();
        playSound(`nowboarding_${notif.args.sound}`);
      } else if (notif.type == "weather") {
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
      const pax = this.gamedatas.pax[paxId];
      if (this.isCurrentPlayerActive()) {
        console.log(`takePaxAction ${pax.id} ${pax.status} ${pax.location}`, pax);
        if (pax.status == "SEAT" && pax.playerId == this.player_id) {
          this.takeAction("deplane", { paxId: pax.id }).catch((error) => {
            if (error == "deplaneConfirm") {
              const msg = this.format_string_recursive(_("This passenger will remain angry ${anger} if you return them to ${location} where they just boarded your plane"), {
                anger: pax.anger,
                location: pax.location,
              });
              this.confirmationDialog(msg, () => {
                this.takeAction("deplane", { paxId: pax.id, confirm: true });
              });
            }
          });
        } else {
          this.takeAction("board", { paxId: pax.id });
        }
      }
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

    // Rendering
    // ----------------------------------------------------------------------

    renderCommon() {
      let commonEl = document.getElementById("nbcommon");
      if (!commonEl) {
        const parentEl = document.getElementById("player_boards");
        parentEl.insertAdjacentHTML(
          "beforebegin",
          `<div id="nbcommon" class="player-board">
  <div class="section">
    <div class="lbl">${_("Complaints")}</div>
    <div class="nbtag"><i class="icon complaint"></i> <span id="nbcommon-complaint"></span></div>
  </div>
  <div class="section">
    <div class="lbl">${_("Time")}</div>
    <div class="nbtag hour"><i class="icon"></i> <span></span></div>
  </div>
  <div class="section">
    <div class="lbl">${_("Map Size")}</div>
    <div class="nbtag"><input type="range" id="nbrange" min="40" max="100" step="2" value="100"></div>
  </div>
</div>`
        );
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

      const hourEl = commonEl.querySelector(".hour");
      const hourIconEl = hourEl.querySelector(".icon");
      const hourTextEl = hourEl.querySelector("span");
      this.swapClass(hourIconEl, "hour-", `hour-${this.gamedatas.hour.hour}`);
      let hourTxt = _(this.gamedatas.hour.hourDesc);
      if (this.gamedatas.hour.round) {
        hourTxt += ` (${this.gamedatas.hour.round}/${this.gamedatas.hour.total})`;
      }
      hourTextEl.textContent = hourTxt;
      const complaintTextEl = document.getElementById("nbcommon-complaint");
      complaintTextEl.textContent = `${this.gamedatas.complaint}/3`;
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
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="plane-${plane.id}" class="plane node node-${plane.location}"><div id="planeicon-${plane.id}" class="icon plane-${plane.alliances[0]}"></div></div>`);
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

      // Add/remove alliances tag
      if (plane.alliances.length > 0) {
        for (const alliance of plane.alliances) {
          const allianceEl = alliancesEl.querySelector(`.alliance-${alliance}`);
          if (!allianceEl) {
            alliancesEl.insertAdjacentHTML("beforeend", `<div class="nbtag alliance alliance-${alliance}"><i class="icon logo-${alliance}"></i> ${alliance}</div>`);
          }
        }
      } else {
        alliancesEl.textContent = "";
      }

      let gaugesEl = document.getElementById(`gauges-${plane.id}`);
      if (gaugesEl == null) {
        const parentEl = document.getElementById(`player_board_${plane.id}`);
        parentEl.insertAdjacentHTML(
          "beforeend",
          `<div id="gauges-${plane.id}" class="gauges">
  <div class="nbtag cash" id="gauge-cash-${plane.id}"></div>
  <div class="nbtag speed"><i class="icon speed"></i> <span id="gauge-speed-${plane.id}"></span></div>
</div>`
        );
        gaugesEl = document.getElementById(`gauges-${plane.id}`);
      }

      // Update cash tag
      const cashEl = document.getElementById(`gauge-cash-${plane.id}`);
      cashEl.textContent = `\$${plane.cashRemain}`;

      // Update speed tag
      const speedEl = document.getElementById(`gauge-speed-${plane.id}`);
      speedEl.textContent = `${plane.speedRemain}/${plane.speed}`;

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
      console.log(`onEnterMapManifest ${manifestId}`);
      const manifestEl = document.getElementById(`manifest-${manifestId}`);
      manifestEl.classList.add("is-active");
      const markerEl = document.querySelector(`#nbmap .marker.node.node-${manifestId}`);
      markerEl.classList.add("is-active", "pinging");
    },

    onLeaveMapManifest(manifestId) {
      console.log(`onLeaveMapManifest for ${manifestId}`);
      const manifestEl = document.getElementById(`manifest-${manifestId}`);
      manifestEl.classList.remove("is-active");
      const markerEl = document.querySelector(`#nbmap .marker.node.node-${manifestId}`);
      markerEl.classList.remove("is-active", "pinging");
    },

    renderPax(pax) {
      console.log(`🧑 Render pax ${pax.id}: location=${pax.location}, playerId=${pax.playerId}, status=${pax.status}`);

      // Determine where the pax is and where it belongs
      let paxEl = document.getElementById(`pax-${pax.id}`);
      let listEl = null;
      if (pax.status == "SECRET" || pax.status == "PORT") {
        // Belong in an airport
        listEl = document.getElementById(`paxlist-${pax.location}`);
      } else if (pax.status == "SEAT") {
        // Belongs in a plane
        listEl = document.getElementById(`paxlist-${pax.playerId}`);
      }

      if (!listEl) {
        // Pax shouldn't exist
        if (paxEl) {
          this.deletePax(pax);
        } else {
          console.warn("Why did we receive this pax?");
        }
        return;
      }

      if (!paxEl) {
        // Pax doesn't exist but should
        // Create new pax
        console.log("Welcome, new passenger!");
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
      const isVip = Math.random() >= 0.7;

      vipEl.textContent = isVip ? "VIP/FIRST IN LINE" : "";
      if (pax.status == "SECRET") {
        paxEl.style.order = 5;
        paxEl.classList.add("is-secret");
        angerIconEl.classList.add("spinner");
        angerCountEl.textContent = "?";
        originEl.textContent = _("WELCOME ABOARD");
      } else {
        paxEl.style.order = 4 - pax.anger;
        paxEl.classList.remove("is-secret");
        cashEl.textContent = `$${pax.cash}`;
        originEl.textContent = `${pax.origin} » `;
        destinationEl.textContent = pax.destination;
        this.swapClass(angerEl, "anger-", `anger-${pax.anger}`);
        this.swapClass(angerIconEl, ["spinner", "anger-"], `anger-${pax.anger}`);
        angerIconEl.classList.toggle("pinging", pax.anger == 3);
        angerCountEl.textContent = pax.anger;
      }

      // Move the pax (if necessary)
      this.movePax(pax, paxEl, listEl);
    },

    movePax(pax, paxEl, listEl) {
      if (paxEl.parentElement == listEl) {
        // Already correct, nothing to do
        return;
      }
      console.log(`🧑 Move pax ${pax.id} to ${listEl.id}`);
      listEl.appendChild(paxEl);
    },

    deletePax(pax) {
      console.log(`❌ Delete passenger ${pax.id} (${pax.status})`);
      this.getElement(`pax-${pax.id}`)?.remove();
    },

    renderBuys(buys) {
      const parentEl = document.getElementById("generalactions");
      parentEl.insertAdjacentHTML("beforeend", `<div id="nbbuys" class="buys">`);
      const buysEl = document.getElementById("nbbuys");
      for (const i in buys) {
        const buy = buys[i];
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
        if (!buy.enabled) {
          cssClass += " ghostbutton";
        }

        buysEl.insertAdjacentHTML("beforeend", `<div id="${id}" class="action-button bgabutton ${cssClass}">${icon}${txt}</div>`);
        const buyEl = document.getElementById(id);
        if (buy.enabled) {
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
