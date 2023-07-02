define(["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter"], function (dojo, declare) {
  let lastErrorCode = null;
  return declare("bgagame.nowboarding", ebg.core.gamegui, {
    constructor() {
      dojo.place("loader_mask", "overall-content", "before");
    },

    setup(gamedatas) {
      console.log("🐣 Setup", gamedatas);

      // TODO!
      dojo.place(`<link rel="stylesheet" type="text/css" href="https://studio.boardgamearena.com:8084/data/themereleases/current/games/nowboarding/999999-9999/index.css?${Date.now()}"/>`, document.head, "last");

      // Setup map
      const playerCount = Object.keys(gamedatas.players).length;
      this.gameEl = document.getElementById("NMap");
      this.gameEl.classList.add(playerCount >= 4 ? "map45" : "map23");
      const manifestContainer = {
        SEA: "manifests-top",
        SFO: "manifests-top",
        DEN: "manifests-top",
        ORD: "manifests-top",
        JFK: "manifests-right",
        ATL: "manifests-right",
        LAX: "manifests-bottom",
        DFW: "manifests-bottom",
        MIA: "manifests-bottom",
      };
      for (const node of gamedatas.map.nodes) {
        dojo.place(`<div id="node-${node}" class="marker node node-${node}"></div>`, this.gameEl);
        if (node.length == 3) {
          this.renderManifest(node, manifestContainer[node]);
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
            this.createPlane(plane);
          }
          this.renderManifest(planeId, `player_board_${planeId}`);
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
      dojo.subscribe("buildReset", this, "onNotify");
      dojo.subscribe("buildPrimary", this, "onNotify");
      dojo.subscribe("move", this, "onNotify");
      this.notifqueue.setSynchronous("move", 1000);
      dojo.subscribe("pax", this, "onNotify");
      dojo.subscribe("planes", this, "onNotify");
      dojo.subscribe("weather", this, "onNotify");

      // Setup preferences
      this.setupPrefs();

      // Production bug report handler
      dojo.subscribe("loadBug", this, function loadBug(n) {
        function fetchNextUrl() {
          var url = n.args.urls.shift();
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
      // this.gameEl.addEventListener("mousemove", debounce(testHandler, 50));
      // this.gameEl.addEventListener("click", debounce(testHandler, 50));
    },

    setupPrefs() {
      // Extract the ID and value from the UI control
      var _this = this;
      function onchange(e) {
        var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
        if (!match) {
          return;
        }
        var id = +match[1];
        var value = +e.target.value;
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
        if (args.allianceFancy) {
          args.allianceFancy = `<span class="logfancy alliance alliance-${args.allianceFancy}"><i class="icon logo logo-${args.allianceFancy}"></i> ${args.allianceFancy}</span>`;
        }
        if (args.seatFancy) {
          args.seatFancy = `<span class="logfancy seat"><i class="icon seat"></i> ${args.seatFancy}</span>`;
        }
        if (args.speedFancy) {
          args.speedFancy = `<span class="logfancy speed"><i class="icon speed"></i> ${args.speedFancy}</span>`;
        }
        if (args.locationFancy) {
          args.locationFancy = `<b>${args.locationFancy}</b>`;
        }
        args.processed = true;
      }
      return this.inherited(arguments);
    },

    /* @Override */
    showMessage: function (msg, type) {
      if (type == "error") {
        const lastErrorCode = msg.startsWith("!!!") ? msg.substring(3) : null;
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
              let cssClass = null;
              let txt = "";
              if (buy.type == "ALLIANCE") {
                cssClass = `alliance-${buy.alliance}`;
                txt = `<div class="icon alliance logo-${buy.alliance}"></div>${buy.alliance} Alliance`;
              } else if (buy.type == "SEAT") {
                txt = `<div class="icon seat"></div>Seat ${buy.seat}`;
              } else if (buy.type == "SPEED") {
                txt = `<div class="icon speed"></div>Speed ${buy.speed}`;
              } else if (buy.type == "TEMP_SEAT") {
                txt = `<div class="icon seat"></div>Temporary Seat`;
              } else if (buy.type == "TEMP_SPEED") {
                txt = `<div class="icon speed"></div>Temporary Speed`;
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
              if (cssClass) {
                this.swapClass(id, "bgabutton_gray", cssClass);
              }
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
                const el = dojo.place(`<div id="move-${move.location}" class="move node node-${move.location} gradient-${alliance}">${move.fuel}</div>`, this.gameEl);
                dojo.connect(el, "onclick", () => {
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
        this.deletePlane(notif.args.plane);
      } else if (notif.type == "buildPrimary") {
        // Create plane
        this.gamedatas.players[notif.args.player_id].color = notif.args.color;
        console.log("plane is ", JSON.stringify(notif.args.plane));
        this.createPlane(notif.args.plane);
      } else if (notif.type == "move") {
        if (notif.args.player_id == this.player_id) {
          // Erase possible moves
          this.deleteMoves();
        }
        this.rotatePlane(notif.args.plane);
        this.movePlane(notif.args.plane);
        this.updatePlane(notif.args.plane);
      } else if (notif.type == "pax") {
        for (const paxId in notif.args.pax) {
          const pax = notif.args.pax[paxId];
          this.gamedatas.pax[paxId] = pax;
          this.renderPax(pax);
        }
      } else if (notif.type == "planes") {
        for (const plane of notif.args.planes) {
          this.updatePlane(plane);
        }
      } else if (notif.type == "weather") {
        this.deleteWeather();
        for (const location in notif.args.weather) {
          const token = notif.args.weather[location];
          this.createWeather(location, token);
        }
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
      console.log(`takePaxAction ${pax.id} ${pax.status} ${pax.location}`, pax);
      if (pax.status == "PORT") {
        this.takeAction("enplane", { paxId: pax.id });
      } else if (pax.status == "SEAT") {
        this.takeAction("deplane", { paxId: pax.id });
      }
    },

    swapClass(el, toRemove, toAdd) {
      if (typeof el == "string") {
        el = document.getElementById(el);
      }
      if (toRemove) {
        if (typeof toRemove == "string") {
          toRemove = [toRemove];
        }
        const classList = [...el.classList];
        classList.forEach((className) => {
          for (const c of toRemove) {
            if (className.startsWith(c)) {
              el.classList.remove(c);
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
      console.log(`🌦️ Create weather ${token} at ${location}`);
      dojo.place(`<div id="weather-${location}" class="weather node node-${location}"><i class="icon weather weather-${token}"></i></div>`, this.gameEl);
    },

    deleteWeather(weather) {
      console.log(`❌ Delete weather`);
      dojo.query("#NMap .weather.node").forEach((el) => dojo.destroy(el));
    },

    createPlane(plane) {
      console.log(`✈️ Create plane ${plane.id} (${plane.alliance}) at ${plane.location}`);
      this.gamedatas.planes[plane.id] = plane;
      const rotation = this.getRotation(plane);
      let style = rotation ? `transform: rotate(${rotation}deg)` : "";
      dojo.place(
        `<div id="plane-${plane.id}" class="plane node node-${plane.location}">
          <div class="icon plane-${plane.alliance}" style="${style}"></div>
        </div>`,
        this.gameEl
      );
      this.swapClass(`overall_player_board_${plane.id}`, "alliance-", `alliance-${plane.alliance}`);
    },

    deletePlane(plane) {
      console.log(`❌ Delete plane ${plane.id}`);
      dojo.destroy(`plane-${plane.id}`);
      this.swapClass(`overall_player_board_${plane.id}`, "alliance-");
    },

    movePlane(plane) {
      console.log(`✈️ Move plane ${plane.id} (${plane.alliance}) to ${plane.location}`);
      this.swapClass(`plane-${plane.id}`, "node-", `node-${plane.location}`);
    },

    rotatePlane(plane) {
      const rotation = this.getRotation(plane);
      console.log(`⤴️ Rotate plane ${plane.id} (${plane.alliance}) to ${rotation}deg`);
      dojo
        .query(`#plane-${plane.id} .icon`)
        .at(0)
        .style({
          transform: `rotate(${rotation}deg)`,
        });
    },

    updatePlane(plane) {
      console.log(`✈️ Update plane ${plane.id} (${plane.alliance})`);
      this.gamedatas.planes[plane.id] = plane;
      let el = document.getElementById(`plane-${plane.id}`);
      if (el == null) {
      }
      // console.log(`💵 Update plane ${plane.id} (${plane.alliance}) to ${plane.speedRemain} speed remaining, ${plane.seatRemain} seats remaining`);
      // dojo.query(`#plane-${plane.id} .planestats`).at(0).text(`${plane.speedRemain}/${plane.seatRemain}`);
    },

    deleteMoves() {
      console.log(`❌ Delete possible moves`);
      dojo.query("#NMap .move.node").forEach((el) => dojo.destroy(el));
    },

    renderManifest(manifestId, parentEl) {
      dojo.place(
        `<div id="manifest-${manifestId}" class="manifest">
  <div class="location">${manifestId}</div>
  <div id="paxlist-${manifestId}" class="paxlist"></div>
</div>`,
        parentEl
      );
    },

    renderPax(pax) {
      console.log(`🧑 Render pax ${pax.id}`);

      // Determine where it is
      let paxEl = document.getElementById(`pax-${pax.id}`);

      // Determine where it belongs
      let manifestEl = null;
      if (pax.status == "SECRET" || pax.status == "PORT") {
        // At the airport
        manifestEl = document.getElementById(`paxlist-${pax.location}`);
      } else if (pax.status == "SEAT") {
        // In transit
        manifestEl = document.getElementById(`paxlist-${pax.playerId}`);
      }

      if (paxEl && !manifestEl) {
        // Delete old pax
        this.deletePax(pax);
        return;
      }

      if (!paxEl && manifestEl) {
        // Create new pax
        console.log("Welcome, new passenger!");
        manifestEl.insertAdjacentHTML(
          "beforeend",
          `<div id="pax-${pax.id}" class="pax">
  <div class="anger">
    <div class="ping"></div>
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
      if (isVip) {
        vipEl.textContent = "VIP/FIRST IN LINE";
      } else {
        vipEl.textContent = "";
      }
      if (pax.status == "SECRET") {
        paxEl.classList.add("is-secret");
        angerIconEl.classList.add("spinner");
        angerCountEl.textContent = "...";
        originEl.textContent = _("WELCOME ABOARD");
      } else {
        paxEl.classList.remove("is-secret");
        cashEl.textContent = `$${pax.cash}`;
        originEl.textContent = `${pax.origin} » `;
        destinationEl.textContent = pax.destination;
        this.swapClass(angerEl, "anger-", `anger-${pax.anger}`);
        this.swapClass(angerIconEl, ["spinner", "anger-"], `anger-${pax.anger}`);
        angerCountEl.textContent = pax.anger;
      }

      // Move to the correct manifest (if necessary)
      this.movePax(pax, paxEl, manifestEl);
    },

    movePax(pax, paxEl, manifestEl) {
      if (paxEl.parentElement == manifestEl) {
        // Already correct, nothing to do
        return;
      }
      console.log(`🧑 Move pax ${pax.id} to ${manifestEl.id}`);
      manifestEl.appendChild(paxEl);
    },

    deletePax(pax) {
      console.log(`❌ Delete passenger ${pax.id} (${pax.status})`);
      dojo.destroy(`pax-${pax.id}`);
    },
  });
});
