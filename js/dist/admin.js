/******/ (() => { // webpackBootstrap
/******/ 	// runtime can't be in strict mode because a global variable is assign and maybe created.
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/defineProperty.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _defineProperty)
/* harmony export */ });
/* harmony import */ var _toPropertyKey_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./toPropertyKey.js */ "./node_modules/@babel/runtime/helpers/esm/toPropertyKey.js");

function _defineProperty(e, r, t) {
  return (r = (0,_toPropertyKey_js__WEBPACK_IMPORTED_MODULE_0__["default"])(r)) in e ? Object.defineProperty(e, r, {
    value: t,
    enumerable: !0,
    configurable: !0,
    writable: !0
  }) : e[r] = t, e;
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/toPrimitive.js":
/*!****************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/toPrimitive.js ***!
  \****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ toPrimitive)
/* harmony export */ });
/* harmony import */ var _typeof_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./typeof.js */ "./node_modules/@babel/runtime/helpers/esm/typeof.js");

function toPrimitive(t, r) {
  if ("object" != (0,_typeof_js__WEBPACK_IMPORTED_MODULE_0__["default"])(t) || !t) return t;
  var e = t[Symbol.toPrimitive];
  if (void 0 !== e) {
    var i = e.call(t, r || "default");
    if ("object" != (0,_typeof_js__WEBPACK_IMPORTED_MODULE_0__["default"])(i)) return i;
    throw new TypeError("@@toPrimitive must return a primitive value.");
  }
  return ("string" === r ? String : Number)(t);
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/toPropertyKey.js":
/*!******************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/toPropertyKey.js ***!
  \******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ toPropertyKey)
/* harmony export */ });
/* harmony import */ var _typeof_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./typeof.js */ "./node_modules/@babel/runtime/helpers/esm/typeof.js");
/* harmony import */ var _toPrimitive_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./toPrimitive.js */ "./node_modules/@babel/runtime/helpers/esm/toPrimitive.js");


function toPropertyKey(t) {
  var i = (0,_toPrimitive_js__WEBPACK_IMPORTED_MODULE_1__["default"])(t, "string");
  return "symbol" == (0,_typeof_js__WEBPACK_IMPORTED_MODULE_0__["default"])(i) ? i : i + "";
}


/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/typeof.js":
/*!***********************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/typeof.js ***!
  \***********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _typeof)
/* harmony export */ });
function _typeof(o) {
  "@babel/helpers - typeof";

  return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) {
    return typeof o;
  } : function (o) {
    return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o;
  }, _typeof(o);
}


/***/ }),

/***/ "./src/admin/components/HorizonStatsWidget.tsx":
/*!*****************************************************!*\
  !*** ./src/admin/components/HorizonStatsWidget.tsx ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ HorizonStatsWidget)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_esm_defineProperty__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/esm/defineProperty */ "./node_modules/@babel/runtime/helpers/esm/defineProperty.js");
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! flarum/admin/app */ "flarum/admin/app");
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var flarum_admin_components_DashboardWidget__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! flarum/admin/components/DashboardWidget */ "flarum/admin/components/DashboardWidget");
/* harmony import */ var flarum_admin_components_DashboardWidget__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_components_DashboardWidget__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var flarum_common_components_LoadingIndicator__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! flarum/common/components/LoadingIndicator */ "flarum/common/components/LoadingIndicator");
/* harmony import */ var flarum_common_components_LoadingIndicator__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(flarum_common_components_LoadingIndicator__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var flarum_common_components_Button__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! flarum/common/components/Button */ "flarum/common/components/Button");
/* harmony import */ var flarum_common_components_Button__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(flarum_common_components_Button__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var flarum_common_components_LinkButton__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! flarum/common/components/LinkButton */ "flarum/common/components/LinkButton");
/* harmony import */ var flarum_common_components_LinkButton__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(flarum_common_components_LinkButton__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var flarum_common_components_Tooltip__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! flarum/common/components/Tooltip */ "flarum/common/components/Tooltip");
/* harmony import */ var flarum_common_components_Tooltip__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(flarum_common_components_Tooltip__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var flarum_common_components_Switch__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! flarum/common/components/Switch */ "flarum/common/components/Switch");
/* harmony import */ var flarum_common_components_Switch__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(flarum_common_components_Switch__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var flarum_common_components_Icon__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! flarum/common/components/Icon */ "flarum/common/components/Icon");
/* harmony import */ var flarum_common_components_Icon__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(flarum_common_components_Icon__WEBPACK_IMPORTED_MODULE_8__);
/* harmony import */ var flarum_common_utils_ItemList__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! flarum/common/utils/ItemList */ "flarum/common/utils/ItemList");
/* harmony import */ var flarum_common_utils_ItemList__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(flarum_common_utils_ItemList__WEBPACK_IMPORTED_MODULE_9__);










class HorizonStatsWidget extends (flarum_admin_components_DashboardWidget__WEBPACK_IMPORTED_MODULE_2___default()) {
  constructor() {
    super(...arguments);
    (0,_babel_runtime_helpers_esm_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(this, "loading", true);
    (0,_babel_runtime_helpers_esm_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(this, "data", {});
    (0,_babel_runtime_helpers_esm_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(this, "autoRefreshEnabled", false);
    (0,_babel_runtime_helpers_esm_defineProperty__WEBPACK_IMPORTED_MODULE_0__["default"])(this, "autoRefreshInterval", void 0);
  }
  oncreate(vnode) {
    super.oncreate(vnode);
    this.loadHorizonStats();
  }
  onremove() {
    this.clearAutoRefresh();
  }
  async loadHorizonStats() {
    this.loading = true;
    m.redraw();
    const data = await flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().request({
      method: 'GET',
      url: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().forum.attribute('adminUrl') + '/horizon/api/stats'
    });
    this.data = data;
    this.loading = false;
    m.redraw();
  }
  toggleAutoRefresh(enabled) {
    this.autoRefreshEnabled = enabled;
    if (enabled) {
      this.setAutoRefresh();
    } else {
      this.clearAutoRefresh();
    }
  }
  setAutoRefresh() {
    this.clearAutoRefresh();
    this.autoRefreshInterval = setInterval(() => this.loadHorizonStats(), 5000);
  }
  clearAutoRefresh() {
    if (this.autoRefreshInterval) {
      clearInterval(this.autoRefreshInterval);
      this.autoRefreshInterval = undefined;
    }
  }
  className() {
    return 'HorizonStatsWidget';
  }
  content() {
    return m("div", {
      className: "HorizonStatsWidget-container"
    }, m("div", {
      className: "HorizonStatsWidget-header"
    }, m("h4", {
      className: "HorizonStatsWidget-title"
    }, flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.heading')), m("div", {
      className: "HorizonStatsWidget-controls"
    }, m((flarum_common_components_Tooltip__WEBPACK_IMPORTED_MODULE_6___default()), {
      text: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.refresh')
    }, m((flarum_common_components_Button__WEBPACK_IMPORTED_MODULE_4___default()), {
      className: "Button Button--icon",
      icon: "fas fa-sync-alt",
      onclick: () => this.loadHorizonStats(),
      disabled: this.loading || this.autoRefreshEnabled
    })), m((flarum_common_components_LinkButton__WEBPACK_IMPORTED_MODULE_5___default()), {
      className: "Button",
      icon: "fas fa-external-link-alt",
      href: flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().forum.attribute('adminUrl') + '/horizon',
      target: "_blank",
      external: true
    }, flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.full_dashboard')))), m("div", {
      className: "HorizonStatsWidget-grid"
    }, this.renderStatsSection()), m("div", {
      className: "HorizonStatsWidget-footer"
    }, m((flarum_common_components_Switch__WEBPACK_IMPORTED_MODULE_7___default()), {
      state: this.autoRefreshEnabled,
      onchange: this.toggleAutoRefresh.bind(this),
      loading: this.loading
    }, flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.auto_refresh'))));
  }
  renderStatsSection() {
    const {
      jobsPerMinute,
      recentJobs,
      recentlyFailed,
      status,
      processes,
      queueWithMaxRuntime,
      queueWithMaxThroughput
    } = this.data;
    const redis_stats = this.data.redis_stats ?? {};
    return m('[', null, this.renderStatusIndicator(status), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.redis-used-memory'), redis_stats.memory_used), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.redis-peak-memory'), redis_stats.memory_peak), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.redis-max-memory'), redis_stats.memory_max ?? 'auto'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.redis-memory-policy'), redis_stats.memory_max_policy, flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.redis-memory-policy-tooltip'), 'https://redis.io/docs/latest/develop/reference/eviction/'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.redis-ops-per-sec'), redis_stats.ops_per_sec?.toString() ?? '0'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.redis-connected-clients'), redis_stats.connected_clients?.toString() ?? '0'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.redis-blocked-clients'), redis_stats.blocked_clients?.toString() ?? '0'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.jobs-per-minute'), jobsPerMinute?.toString() ?? '0'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.jobs-past-hour'), recentJobs?.toString() ?? '0'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.failed-last-seconds'), recentlyFailed?.toString() ?? '0'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.total-processes'), processes?.toString() ?? '0'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.max-wait-time'), '-'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.max-runtime'), queueWithMaxRuntime ?? '-'), this.renderStat(flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.max-throughput'), queueWithMaxThroughput ?? '-'));
  }
  renderStat(label, value, infoLabel, infoUrl) {
    if (infoLabel === void 0) {
      infoLabel = undefined;
    }
    if (infoUrl === void 0) {
      infoUrl = undefined;
    }
    return m("div", {
      className: "HorizonStatsWidget-stat"
    }, this.statItems(label, value, infoLabel, infoUrl).toArray());
  }
  statItems(label, value, infoLabel, infoUrl) {
    const items = new (flarum_common_utils_ItemList__WEBPACK_IMPORTED_MODULE_9___default())();
    items.add('label', m("small", null, this.labelItems(label, infoLabel, infoUrl).toArray()), 100);
    items.add('value', m("p", null, value || !this.loading ? value : m((flarum_common_components_LoadingIndicator__WEBPACK_IMPORTED_MODULE_3___default()), {
      size: "small",
      display: "inline"
    })), 80);
    return items;
  }
  labelItems(label, infoLabel, infoUrl) {
    const items = new (flarum_common_utils_ItemList__WEBPACK_IMPORTED_MODULE_9___default())();
    items.add('label', m("span", null, label), 100);
    if (infoLabel && infoUrl) {
      items.add('info', m((flarum_common_components_Tooltip__WEBPACK_IMPORTED_MODULE_6___default()), {
        text: infoLabel
      }, m("span", null, ' ', m((flarum_common_components_LinkButton__WEBPACK_IMPORTED_MODULE_5___default()), {
        href: infoUrl,
        external: true,
        target: "_blank",
        icon: "fas fa-info-circle"
      }))), 90);
    }
    return items;
  }
  renderStatusIndicator(status) {
    const iconClass = status === 'running' ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger';
    return m("div", {
      className: "HorizonStatsWidget-stat"
    }, m("small", null, flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans('fof-horizon.admin.stats.data.status.label')), m("p", null, m((flarum_common_components_Icon__WEBPACK_IMPORTED_MODULE_8___default()), {
      name: iconClass
    }), " ", status ? flarum_admin_app__WEBPACK_IMPORTED_MODULE_1___default().translator.trans(`fof-horizon.admin.stats.data.status.${status}`) : ''));
  }
}
flarum.reg.add('fof-horizon', 'admin/components/HorizonStatsWidget', HorizonStatsWidget);

/***/ }),

/***/ "./src/admin/components/SettingsPage.js":
/*!**********************************************!*\
  !*** ./src/admin/components/SettingsPage.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SettingsPage)
/* harmony export */ });
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! flarum/admin/app */ "flarum/admin/app");
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_app__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var flarum_admin_components_ExtensionPage__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! flarum/admin/components/ExtensionPage */ "flarum/admin/components/ExtensionPage");
/* harmony import */ var flarum_admin_components_ExtensionPage__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_components_ExtensionPage__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var flarum_common_components_LinkButton__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! flarum/common/components/LinkButton */ "flarum/common/components/LinkButton");
/* harmony import */ var flarum_common_components_LinkButton__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(flarum_common_components_LinkButton__WEBPACK_IMPORTED_MODULE_2__);



class SettingsPage extends (flarum_admin_components_ExtensionPage__WEBPACK_IMPORTED_MODULE_1___default()) {
  content() {
    const horizonUrl = flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().forum.attribute('adminUrl') + '/horizon';
    return m("div", {
      className: "container"
    }, m("div", {
      className: "HorizonSettingsPage"
    }, m((flarum_common_components_LinkButton__WEBPACK_IMPORTED_MODULE_2___default()), {
      icon: "fas fa-external-link-alt",
      className: "Button",
      href: horizonUrl,
      external: true,
      target: "_blank"
    }, flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.stats.full_dashboard')), m("hr", null), m("div", {
      className: "HorizonSettingsPage-settings"
    }, m("div", {
      className: "Form-group"
    }, m("div", {
      className: "HorizonSettingsPage-trim"
    }, m("h3", null, flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_title')), m("p", {
      className: "helpText"
    }, flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_help')), this.buildSettingComponent({
      setting: 'fof-horizon.trim.recent',
      type: 'number',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_recent'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_recent_help')
    }), this.buildSettingComponent({
      setting: 'fof-horizon.trim.pending',
      type: 'number',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_pending'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_pending_help')
    }), this.buildSettingComponent({
      setting: 'fof-horizon.trim.completed',
      type: 'number',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_completed'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_completed_help')
    }), this.buildSettingComponent({
      setting: 'fof-horizon.trim.recent_failed',
      type: 'number',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_recent_failed'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_recent_failed_help')
    }), this.buildSettingComponent({
      setting: 'fof-horizon.trim.failed',
      type: 'number',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_failed'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_failed_help')
    }), this.buildSettingComponent({
      setting: 'fof-horizon.trim.monitored',
      type: 'number',
      label: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_monitored'),
      help: flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().translator.trans('fof-horizon.admin.settings.trim_monitored_help')
    })), m("br", null), this.submitButton()))));
  }
}
flarum.reg.add('fof-horizon', 'admin/components/SettingsPage', SettingsPage);

/***/ }),

/***/ "./src/admin/extendDashboardPage.tsx":
/*!*******************************************!*\
  !*** ./src/admin/extendDashboardPage.tsx ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ extendDashboardPage)
/* harmony export */ });
/* harmony import */ var flarum_common_extend__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! flarum/common/extend */ "flarum/common/extend");
/* harmony import */ var flarum_common_extend__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(flarum_common_extend__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var flarum_admin_components_DashboardPage__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! flarum/admin/components/DashboardPage */ "flarum/admin/components/DashboardPage");
/* harmony import */ var flarum_admin_components_DashboardPage__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_components_DashboardPage__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _components_HorizonStatsWidget__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./components/HorizonStatsWidget */ "./src/admin/components/HorizonStatsWidget.tsx");



function extendDashboardPage() {
  (0,flarum_common_extend__WEBPACK_IMPORTED_MODULE_0__.extend)((flarum_admin_components_DashboardPage__WEBPACK_IMPORTED_MODULE_1___default().prototype), 'availableWidgets', function (widgets) {
    widgets.add('horizon-stats', m(_components_HorizonStatsWidget__WEBPACK_IMPORTED_MODULE_2__["default"], null), 30);
  });
}

/***/ }),

/***/ "./src/admin/extendStatusWidget.tsx":
/*!******************************************!*\
  !*** ./src/admin/extendStatusWidget.tsx ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ extendStatusWidget)
/* harmony export */ });
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! flarum/admin/app */ "flarum/admin/app");
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_app__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var flarum_common_extend__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! flarum/common/extend */ "flarum/common/extend");
/* harmony import */ var flarum_common_extend__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(flarum_common_extend__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var flarum_admin_components_StatusWidget__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! flarum/admin/components/StatusWidget */ "flarum/admin/components/StatusWidget");
/* harmony import */ var flarum_admin_components_StatusWidget__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_components_StatusWidget__WEBPACK_IMPORTED_MODULE_2__);



function extendStatusWidget() {
  (0,flarum_common_extend__WEBPACK_IMPORTED_MODULE_1__.extend)((flarum_admin_components_StatusWidget__WEBPACK_IMPORTED_MODULE_2___default().prototype), 'items', function (items) {
    items.add('version-redis', [m("strong", null, "Redis"), m("br", null), (flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().data).redisVersion], 75);
  });
}

/***/ }),

/***/ "./src/admin/index.ts":
/*!****************************!*\
  !*** ./src/admin/index.ts ***!
  \****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! flarum/admin/app */ "flarum/admin/app");
/* harmony import */ var flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(flarum_admin_app__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_SettingsPage__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./components/SettingsPage */ "./src/admin/components/SettingsPage.js");
/* harmony import */ var _extendStatusWidget__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./extendStatusWidget */ "./src/admin/extendStatusWidget.tsx");
/* harmony import */ var _extendDashboardPage__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./extendDashboardPage */ "./src/admin/extendDashboardPage.tsx");




flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().initializers.add('fof-horizon', () => {
  flarum_admin_app__WEBPACK_IMPORTED_MODULE_0___default().registry.for('fof-horizon').registerPage(_components_SettingsPage__WEBPACK_IMPORTED_MODULE_1__["default"]);
  (0,_extendStatusWidget__WEBPACK_IMPORTED_MODULE_2__["default"])();
  (0,_extendDashboardPage__WEBPACK_IMPORTED_MODULE_3__["default"])();
});

/***/ }),

/***/ "flarum/admin/app":
/*!******************************************************!*\
  !*** external "flarum.reg.get('core', 'admin/app')" ***!
  \******************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'admin/app');

/***/ }),

/***/ "flarum/admin/components/DashboardPage":
/*!***************************************************************************!*\
  !*** external "flarum.reg.get('core', 'admin/components/DashboardPage')" ***!
  \***************************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'admin/components/DashboardPage');

/***/ }),

/***/ "flarum/admin/components/DashboardWidget":
/*!*****************************************************************************!*\
  !*** external "flarum.reg.get('core', 'admin/components/DashboardWidget')" ***!
  \*****************************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'admin/components/DashboardWidget');

/***/ }),

/***/ "flarum/admin/components/ExtensionPage":
/*!***************************************************************************!*\
  !*** external "flarum.reg.get('core', 'admin/components/ExtensionPage')" ***!
  \***************************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'admin/components/ExtensionPage');

/***/ }),

/***/ "flarum/admin/components/StatusWidget":
/*!**************************************************************************!*\
  !*** external "flarum.reg.get('core', 'admin/components/StatusWidget')" ***!
  \**************************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'admin/components/StatusWidget');

/***/ }),

/***/ "flarum/common/components/Button":
/*!*********************************************************************!*\
  !*** external "flarum.reg.get('core', 'common/components/Button')" ***!
  \*********************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'common/components/Button');

/***/ }),

/***/ "flarum/common/components/Icon":
/*!*******************************************************************!*\
  !*** external "flarum.reg.get('core', 'common/components/Icon')" ***!
  \*******************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'common/components/Icon');

/***/ }),

/***/ "flarum/common/components/LinkButton":
/*!*************************************************************************!*\
  !*** external "flarum.reg.get('core', 'common/components/LinkButton')" ***!
  \*************************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'common/components/LinkButton');

/***/ }),

/***/ "flarum/common/components/LoadingIndicator":
/*!*******************************************************************************!*\
  !*** external "flarum.reg.get('core', 'common/components/LoadingIndicator')" ***!
  \*******************************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'common/components/LoadingIndicator');

/***/ }),

/***/ "flarum/common/components/Switch":
/*!*********************************************************************!*\
  !*** external "flarum.reg.get('core', 'common/components/Switch')" ***!
  \*********************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'common/components/Switch');

/***/ }),

/***/ "flarum/common/components/Tooltip":
/*!**********************************************************************!*\
  !*** external "flarum.reg.get('core', 'common/components/Tooltip')" ***!
  \**********************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'common/components/Tooltip');

/***/ }),

/***/ "flarum/common/extend":
/*!**********************************************************!*\
  !*** external "flarum.reg.get('core', 'common/extend')" ***!
  \**********************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'common/extend');

/***/ }),

/***/ "flarum/common/utils/ItemList":
/*!******************************************************************!*\
  !*** external "flarum.reg.get('core', 'common/utils/ItemList')" ***!
  \******************************************************************/
/***/ ((module) => {

"use strict";
module.exports = flarum.reg.get('core', 'common/utils/ItemList');

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		flarum.reg._webpack_runtimes["fof-horizon"] ||= __webpack_require__;// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be in strict mode.
(() => {
"use strict";
/*!******************!*\
  !*** ./admin.ts ***!
  \******************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _src_admin__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./src/admin */ "./src/admin/index.ts");

})();

module.exports = __webpack_exports__;
/******/ })()
;
//# sourceMappingURL=admin.js.map