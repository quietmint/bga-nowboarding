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
        this.mapEl.insertAdjacentHTML("beforeend", `<div id="node-${node}" class="marker node node-${node}"></div>`);
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
          if (plane.location) {
            this.renderPlane(plane);
          }
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
      dojo.subscribe("buildReset", this, "onNotify");
      dojo.subscribe("complaint", this, "onNotify");
      dojo.subscribe("finale", this, "onNotify");
      dojo.subscribe("move", this, "onNotify");
      dojo.subscribe("pax", this, "onNotify");
      dojo.subscribe("planes", this, "onNotify");
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
            load: function (success) {
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
    format_string_recursive: function (log, args) {
      if (log && args && !args.processed) {
        for (const k in args) {
          if (k == "alliance") {
            args.alliance = `<span class="nbtag alliance-${args[k]}"><i class="icon logo-${args[k]}"></i> ${args[k]}</span>`;
          } else if (k == "anger") {
            args.anger = `<span class="nbtag anger-${args[k]}"><i class="icon anger-${args[k]}"></i> ${args[k]}</span>`;
          } else if (k == "seat") {
            args.seat = `<span class="nbtag seat"><i class="icon seat"></i> ${args[k]}</span>`;
          } else if (k == "speed") {
            args.speed = `<span class="nbtag speed"><i class="icon speed"></i> ${args[k]}</span>`;
          } else if (k == "complaint") {
            args[k] = `<span class="nbtag complaint"><i class="icon complaint"></i> ${args[k]}</span>`;
          } else if (k == "cash" || k == "count" || k == "destination" || k == "fast" || k == "location" || k == "origin" || k == "route" || k == "slow") {
            args[k] = `<b>${args[k]}</b>`;
          }
        }
        args.processed = true;
      }
      return this.inherited(arguments);
    },

    /* @Override */
    showMessage: function (msg, type) {
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
        if (this.isCurrentPlayerActive()) {
          if (args?.buys?.length > 0) {
            const buys = args.buys;
            for (const i in buys) {
              const buy = buys[i];
              const id = `button_buy_${i}`;
              let cssClass = "slatebutton";
              let txt = "";
              if (buy.type == "ALLIANCE") {
                cssClass = `alliance-${buy.alliance}`;
                txt = `<div class="icon alliance logo-${buy.alliance}"></div>${buy.alliance}`;
              } else if (buy.type == "SEAT") {
                txt = `<div class="icon seat"></div>${_("Seat")} ${buy.seat}`;
              } else if (buy.type == "SPEED") {
                txt = `<div class="icon speed"></div>${_("Speed")} ${buy.speed}`;
              } else if (buy.type == "TEMP_SEAT") {
                txt = `<div class="icon seat"></div>${_("Temporary Seat")}`;
              } else if (buy.type == "TEMP_SPEED") {
                txt = `<div class="icon speed"></div>${_("Temporary Speed")}`;
              }
              if (buy.cost > 0) {
                txt += ` ($${buy.cost})`;
              }
              this.addActionButton(
                id,
                txt,
                function () {
                  this.takeAction("buy", buy);
                },
                null,
                false,
                "gray"
              );
              this.swapClass(id, "bgabutton_gray", cssClass);
            }
          }

          if (stateName == "preparePrivate") {
            this.addActionButton("button_flightBegin", _("Begin Round"), () => this.takeAction("flightBegin"));
          }

          if (stateName == "flyPrivate") {
            // Update the possible moves
            this.deleteMoves();
            if (args?.moves) {
              const alliance = this.gamedatas.planes[this.player_id].alliance;
              let moveCount = 0;
              for (const i in args.moves) {
                const move = args.moves[i];
                this.mapEl.insertAdjacentHTML("beforeend", `<div id="move-${move.location}" class="move node node-${move.location} gradient-${alliance}">${move.fuel}</div>`);
                const moveEl = document.getElementById(`move-${move.location}`);
                moveEl.addEventListener("click", (e) => {
                  this.takeAction("move", move);
                });
                moveCount++;
              }
              console.log(`🗺️ Add ${moveCount} possible moves`);
            }

            this.addActionButton("button_flightEnd", _("End Round"), () => this.takeAction("flightEnd"), null, false, "red");
          }
        }

        if (stateName == "build" || stateName == "buildAlliance2" || stateName == "buildUpgrade") {
          this.addActionButton("button_buildReset", _("Start Over"), () => this.takeAction("buildReset"));
        }

        if (stateName == "fly" || stateName == "maintenance") {
          this.deleteMoves();
        }
      }
    },

    onNotify: function (notif) {
      console.log(`💬 Notify ${notif.type}`, notif.args);
      if (notif.type == "buildReset") {
        this.gamedatas.planes[notif.args.plane.id] = notif.args.plane;
        this.deletePlane(notif.args.plane);
        this.renderPlaneGauges(notif.args.plane);
        this.renderPlaneManifest(notif.args.plane);
      } else if (notif.type == "buildPrimary") {
        this.gamedatas.players[notif.args.player_id].color = notif.args.color;
        this.gamedatas.planes[notif.args.plane.id] = notif.args.plane;
        this.renderPlane(notif.args.plane);
        this.renderPlaneGauges(notif.args.plane);
      } else if (notif.type == "complaint") {
        playSound("nowboarding_complaint");
        this.gamedatas.complaint = notif.args.total;
        this.renderCommon();
      } else if (notif.type == "finale") {
        playSound("nowboarding_walkway");
        this.gamedatas.hour = notif.args.hour;
        this.gamedatas.hourDesc = notif.args.hourDesc;
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
        if (notif.args.cash) {
          this.disableNextMoveSound();
          playSound("nowboarding_cash");
        }
      } else if (notif.type == "planes") {
        for (const plane of notif.args.planes) {
          this.gamedatas.planes[plane.id] = plane;
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
        this.gamedatas.hour = notif.args.hour;
        this.gamedatas.hourDesc = notif.args.hourDesc;
        this.renderCommon();
      }
    },

    onPrefChange: function (id, value) {
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
              const msg = this.format_string_recursive(_("This passenger boarded your plane in ${location}. Returning them to the same airport will preserve their ${anger} anger."), {
                anger: pax.anger,
                location: pax.location,
              });
              this.confirmationDialog(msg, () => {
                this.takeAction("deplane", { paxId: pax.id, confirm: true });
              });
            }
          });
        } else {
          this.takeAction("enplane", { paxId: pax.id });
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

    createWeather(location, token) {
      console.log(`🌤️ Create weather ${token} at ${location}`);
      this.mapEl.insertAdjacentHTML("beforeend", `<div id="weather-${location}" class="weather node node-${location}"><i class="icon weather-${token}"></i></div>`);
    },

    deleteWeather() {
      console.log(`❌ Delete weather`);
      document.querySelectorAll("#nbmap .weather").forEach((el) => el.remove());
    },

    renderPlane(plane) {
      console.log(`✈️ Create plane ${plane.id} (${plane.alliance}) at ${plane.location}`);
      this.swapClass(`overall_player_board_${plane.id}`, "panel-", `panel-${plane.alliance}`);
      this.mapEl.insertAdjacentHTML(
        "beforeend",
        `<div id="plane-${plane.id}" class="plane node node-${plane.location}">
          <div id="planeicon-${plane.id}" class="icon plane-${plane.alliance}"></div>
        </div>`
      );

      const rotation = this.getRotation(plane);
      if (rotation) {
        const iconEl = document.getElementById(`planeicon-${plane.id}`);
        iconEl.style.transform = `rotate(${rotation}deg)`;
      }
    },

    deletePlane(plane) {
      console.log(`❌ Delete plane ${plane.id}`);
      this.getElement(`plane-${plane.id}`)?.remove();
      document.querySelectorAll(`#gauges-${plane.id} .alliance`).forEach((el) => el.remove());
      this.swapClass(`overall_player_board_${plane.id}`, "panel-");
    },

    movePlane(plane) {
      console.log(`✈️ Move plane ${plane.id} (${plane.alliance}) to ${plane.location}`);
      const rotation = this.getRotation(plane);
      document.querySelector(`#plane-${plane.id} .icon`).style.transform = `rotate(${rotation}deg)`;
      this.swapClass(`plane-${plane.id}`, "node-", `node-${plane.location}`);
    },

    deleteMoves() {
      console.log(`❌ Delete possible moves`);
      document.querySelectorAll("#nbmap .move").forEach((el) => el.remove());
    },

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
</div>`
        );
        commonEl = document.getElementById("nbcommon");
      }

      const hourEl = commonEl.querySelector(".hour");
      const hourIconEl = hourEl.querySelector(".icon");
      const hourTextEl = hourEl.querySelector("span");
      this.swapClass(hourIconEl, "hour-", `hour-${this.gamedatas.hour}`);
      hourTextEl.textContent = this.gamedatas.hourDesc;
      const complaintTextEl = document.getElementById("nbcommon-complaint");
      complaintTextEl.textContent = `${this.gamedatas.complaint}/3`;
    },

    renderPlaneGauges(plane) {
      let gaugesEl = document.getElementById(`gauges-${plane.id}`);
      if (gaugesEl == null) {
        const parentEl = document.getElementById(`player_board_${plane.id}`);
        parentEl.insertAdjacentHTML(
          "beforeend",
          `<div id="gauges-${plane.id}" class="gauges">
  <div class="nbtag speed"><i class="icon speed"></i> <span id="gauge-speed-${plane.id}"></span></div>
</div>`
        );
        gaugesEl = document.getElementById(`gauges-${plane.id}`);
      }

      // Update speed
      const speedEl = document.getElementById(`gauge-speed-${plane.id}`);
      speedEl.textContent = `${plane.speedRemain}/${plane.speed}`;

      // Add/remove alliance tags
      if (plane.alliances) {
        for (const alliance of plane.alliances) {
          const allianceEl = gaugesEl.querySelector(`.alliance-${alliance}`);
          if (!allianceEl) {
            gaugesEl.insertAdjacentHTML("afterbegin", `<div class="nbtag alliance alliance-${alliance}"><i class="icon logo-${alliance}"></i> ${alliance}</div>`);
          }
        }
      }

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
        paxEl.classList.add("is-secret");
        angerIconEl.classList.add("spinner");
        angerCountEl.textContent = "?";
        originEl.textContent = _("WELCOME ABOARD");
      } else {
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
  });
});
