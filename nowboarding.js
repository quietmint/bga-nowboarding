define(["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter"], function (dojo, declare) {
  return declare("bgagame.nowboarding", ebg.core.gamegui, {
    constructor() {},

    setup(gamedatas) {
      console.log("🐣 Setup", this.gamedatas);

      // Setup map
      for (let nodeId in gamedatas.map.nodes) {
        var node = gamedatas.map.nodes[nodeId];
        dojo.place(`<div id="node-${nodeId}" class="marker node ${nodeId}"></div>`, "NMap");
        if (node.weather) {
          this.createWeather(node);
        }
      }

      // Setup planes
      if (gamedatas.planes) {
        for (let planeId in gamedatas.planes) {
          let plane = gamedatas.planes[planeId];
          if (plane.currentNode) {
            this.createPlane(plane);
          }
        }
      }

      // Setup pax

      // Setup player boards
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
      }

      // Setup notifications
      dojo.subscribe("buy", this, "onNotify");
      dojo.subscribe("move", this, "onNotify");
      this.notifqueue.setSynchronous("move");
      dojo.subscribe("stateArgs", this, "onNotify");

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

      // dojo.connect($("NMap"), "onclick", (e) => {
      //   var pos = dojo.position("NMap");
      //   var x = Math.round((e.offsetX / pos.w) * 1000) / 10 + "%";
      //   var y = Math.round((e.offsetY / pos.h) * 1000) / 10 + "%";
      //   dojo.style("testpoint", {
      //     left: x,
      //     top: y,
      //   });
      //   dojo.place("<b>left: " + x + "; top: " + y + ";</b>", "testview", "only");
      // });
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
        if (args.colorFancy) {
          args.colorFancy = `<span class="logfancy color ${args.colorFancy}"><i class="icon color ${args.colorFancy}"></i> ${_(args.colorFancy)}</span>`;
        }
        args.processed = true;
      }
      return this.inherited(arguments);
    },

    // ----------------------------------------------------------------------

    onEnteringState(stateName, args) {
      console.log("Entering state: " + stateName);
    },

    onLeavingState(stateName) {
      console.log("Leaving state: " + stateName);
    },

    onUpdateActionButtons(stateName, args) {
      console.log(`▶️ State ${stateName}`, args);

      if (this.isCurrentPlayerActive()) {
        if (stateName == "build" || stateName == "preflight") {
          if (args[this.player_id] && args[this.player_id].buys && args[this.player_id].buys.length > 0) {
            let buys = args[this.player_id].buys;
            for (let i in buys) {
              let buy = buys[i];
              let id = "button_buy_" + buy.type;
              let txt = "";
              if (buy.type == "COLOR") {
                id += "_" + buy.color;
                txt = `<div class="icon color ${buy.color}"></div>${buy.color} Alliance ($${buy.cost})`;
              } else if (buy.type == "EXTRA_SEAT") {
                txt = `<div class="icon seat"></div>Temporary Seat ($${buy.cost})`;
              } else if (buy.type == "EXTRA_SPEED") {
                txt = `<div class="icon speed"></div>Temporary Engine ($${buy.cost})`;
              } else if (buy.type == "SEAT") {
                txt = `<div class="icon seat"></div>${buy.seat} Seats ($${buy.cost})`;
              } else if (buy.type == "SPEED") {
                txt = `<div class="icon speed"></div>${buy.speed} Engines ($${buy.cost})`;
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
            }
          }
        }

        if (stateName == "preflight") {
          this.addActionButton("begin_button", _("Begin Round"), () => this.takeAction("begin"));
        }

        if (stateName == "flight") {
          this.addActionButton("end_button", _("End Round"), () => this.takeAction("end"), null, false, "red");
        }
      }
    },

    onNotify: function (notif) {
      console.log(`💬 Notify ${notif.type}`, notif.args);
      if (notif.type == "buy") {
        if (notif.args.plane) {
          // Set player color
          this.gamedatas.players[notif.args.plane.id].color = notif.args.plane.colorHex;
          dojo.query(`#player_name_${notif.args.plane.id} a`).style({
            color: `#${notif.args.plane.colorHex}`,
          });
          // Creeate the plane
          this.createPlane(notif.args.plane);
        }
      } else if (notif.type == "stateArgs") {
        // Update the action buttons
        console.log("the old args", JSON.stringify(this.gamedatas.gamestate.args, null, 2));
        console.log("the new args", JSON.stringify(notif.args, null, 2));
        this.gamedatas.gamestate.args = notif.args;
        this.updatePageTitle();
      }
    },

    onPrefChange: function (id, value) {
      console.log("Preference changed", id, value);
    },

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
          if (this.isInterfaceLocked()) {
            throw `Take action ${action} ignored by interface lock`;
          }
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

    createWeather(node) {
      var weatherIcon = node.weather == "FAST" ? "wind" : "storm";
      dojo.place(`<div id="weather-${node.id}" class="weather node ${node.id}"><i class="icon weather ${node.weather}"></i></div>`, "NMap");
    },

    deleteWeather(node) {},

    createPlane(plane) {
      console.log("Creating a plane");
      dojo.place(
        `<div id="plane-${plane.id}" class="plane ${plane.color} node ${plane.currentNode}">
          <div class="planebody icon plane ${plane.color}"></div>
          <div class="planestats">${plane.fuel}/${plane.seats}</div>
        </div>`,
        "NMap"
      );

      if (plane.priorNode != null) {
        this.pointPlane(plane.id, plane.priorNode, plane.currentNode);
      }
    },

    pointPlane(playerId, priorNode, currentNode) {
      let priorPos = dojo.position(`node-${priorNode}`);
      let currentPos = dojo.position(`node-${currentNode}`);
      console.log("positions", priorPos, currentPos);
      let rotation = (Math.atan2(currentPos.y - priorPos.y, currentPos.x - priorPos.x) * 180) / Math.PI + 90;
      console.log("rotation", rotation);
      dojo
        .query(`#plane-${playerId} .planebody`)
        .at(0)
        .style({
          transform: "rotate(" + rotation + "deg)",
        });
    },

    movePlane(playerId, priorNode, currentNode) {},
  });
});
