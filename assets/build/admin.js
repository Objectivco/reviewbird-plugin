/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./assets/src/js/components/ConnectionPanel.jsx":
/*!******************************************************!*\
  !*** ./assets/src/js/components/ConnectionPanel.jsx ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ConnectionPanel)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _StatusIndicator__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./StatusIndicator */ "./assets/src/js/components/StatusIndicator.jsx");




function ConnectionPanel({
  settings,
  onSave,
  saving
}) {
  const handleOAuthConnect = () => {
    // Create a form and submit it via POST to start OAuth flow
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.reviewappAdmin?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const actionField = document.createElement('input');
    actionField.type = 'hidden';
    actionField.name = 'action';
    actionField.value = 'reviewapp_start_oauth';
    form.appendChild(actionField);
    const nonceField = document.createElement('input');
    nonceField.type = 'hidden';
    nonceField.name = 'nonce';
    nonceField.value = settings?.oauth_nonce || '';
    form.appendChild(nonceField);
    document.body.appendChild(form);
    form.submit();
  };
  const handleDisconnect = async () => {
    if (confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Are you sure you want to disconnect from ReviewApp?', 'reviewapp-reviews'))) {
      try {
        await onSave({
          store_token: ''
        });
      } catch (err) {
        // Error handled by parent
      }
    }
  };
  const isConnected = !!settings?.store_token;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "px-6 py-5 border-b border-gray-200 bg-gray-50 flex items-center justify-between"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", {
    className: "text-xl font-semibold text-gray-900"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_StatusIndicator__WEBPACK_IMPORTED_MODULE_3__["default"], {
    status: settings?.connection_status,
    storeId: settings?.store_id
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "px-6 py-5 space-y-6"
  }, !isConnected ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-center py-8"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "mx-auto w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-8 h-8 text-indigo-600",
    fill: "none",
    stroke: "currentColor",
    viewBox: "0 0 24 24"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    strokeWidth: 2,
    d: "M13 10V3L4 14h7v7l9-11h-7z"
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", {
    className: "text-lg font-medium text-gray-900 mb-2"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connect to ReviewApp', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-gray-600 mb-6 max-w-md mx-auto"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Start collecting and displaying reviews by connecting your store to ReviewApp', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    variant: "primary",
    onClick: handleOAuthConnect,
    className: "!bg-indigo-600 hover:!bg-indigo-700 !text-white !px-8 !py-3 !text-base !h-auto !font-medium"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connect Now', 'reviewapp-reviews'))) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "space-y-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-green-50 border border-green-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex items-start"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-green-600 mt-0.5 flex-shrink-0",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", {
    className: "text-sm font-medium text-green-800"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Your store is connected', 'reviewapp-reviews')), settings.store_id && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-green-700 mt-1"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Store ID:', 'reviewapp-reviews'), " ", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("code", {
    className: "font-mono"
  }, settings.store_id))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    type: "button",
    onClick: handleDisconnect,
    disabled: saving,
    className: "inline-flex items-center justify-center px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm border border-red-200 hover:border-red-300 rounded shadow-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2",
    style: {
      boxShadow: 'none'
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Disconnect', 'reviewapp-reviews')))));
}

/***/ }),

/***/ "./assets/src/js/components/LoadingSpinner.jsx":
/*!*****************************************************!*\
  !*** ./assets/src/js/components/LoadingSpinner.jsx ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ LoadingSpinner)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);


function LoadingSpinner() {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex items-center justify-center min-h-screen"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-center"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "mt-4 text-gray-600"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Loading...', 'reviewapp-reviews'))));
}

/***/ }),

/***/ "./assets/src/js/components/ReviewRequestPanel.jsx":
/*!*********************************************************!*\
  !*** ./assets/src/js/components/ReviewRequestPanel.jsx ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ReviewRequestPanel)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);



function ReviewRequestPanel({
  settings,
  onSave,
  saving,
  isConnected
}) {
  const [triggerStatus, setTriggerStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(settings?.review_request_trigger_status || 'completed');
  const handleStatusChange = async e => {
    const status = e.target.value;
    setTriggerStatus(status);
    try {
      await onSave({
        review_request_trigger_status: status
      });
    } catch (err) {
      // Error handled by parent - revert to previous value
      setTriggerStatus(settings?.review_request_trigger_status || 'completed');
    }
  };
  if (!isConnected) {
    return null;
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "px-6 py-5 border-b border-gray-200 bg-gray-50"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", {
    className: "text-xl font-semibold text-gray-900"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Review Requests', 'reviewapp-reviews'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "px-6 py-5 space-y-6"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-gray-600 mb-4"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Review request emails are automatically sent to customers after their orders are fulfilled. Configure which order status triggers the emails below.', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "space-y-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    htmlFor: "trigger-status",
    className: "block text-sm font-medium text-gray-900 mb-2"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Fulfilled Order Status', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("select", {
    id: "trigger-status",
    value: triggerStatus,
    onChange: handleStatusChange,
    disabled: saving,
    className: "block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
  }, settings?.available_order_statuses && Object.entries(settings.available_order_statuses).map(([value, label]) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("option", {
    key: value,
    value: value
  }, label))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "mt-2 text-sm text-gray-500"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('When an order reaches this status, a review request email will be scheduled.', 'reviewapp-reviews'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-gray-50 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-2xl font-bold text-gray-900"
  }, settings?.orders_synced_count || 0), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-sm text-gray-600"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Synced Orders', 'reviewapp-reviews')), settings?.orders_last_synced > 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-xs text-gray-500 mt-1"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Last synced:', 'reviewapp-reviews'), ' ', new Date(settings.orders_last_synced * 1000).toLocaleString()))))));
}

/***/ }),

/***/ "./assets/src/js/components/ReviewSyncPanel.jsx":
/*!******************************************************!*\
  !*** ./assets/src/js/components/ReviewSyncPanel.jsx ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ReviewSyncPanel)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);




function ReviewSyncPanel({
  isConnected,
  productsAreSynced
}) {
  const [syncStatus, setSyncStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(true);
  const [syncing, setSyncing] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (isConnected) {
      loadSyncStatus();
      // Poll for status while syncing
      const interval = setInterval(() => {
        if (syncStatus?.is_syncing) {
          loadSyncStatus();
        }
      }, 2000); // Poll every 2 seconds

      return () => clearInterval(interval);
    }
  }, [isConnected, syncStatus?.is_syncing]);
  const loadSyncStatus = async () => {
    try {
      const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: '/reviewapp/v1/sync/reviews/status'
      });
      setSyncStatus(data);
      setError(null);
    } catch (err) {
      console.error('Failed to load review sync status:', err);
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to load review sync status', 'reviewapp-reviews'));
    } finally {
      setLoading(false);
    }
  };
  const startSync = async () => {
    setSyncing(true);
    setError(null);
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: '/reviewapp/v1/sync/reviews/start',
        method: 'POST'
      });
      // Reload status immediately
      await loadSyncStatus();
    } catch (err) {
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to start review sync', 'reviewapp-reviews'));
    } finally {
      setSyncing(false);
    }
  };
  if (!isConnected) {
    return null;
  }
  if (loading) {
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "bg-white rounded-lg shadow-sm border border-gray-200 p-6"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "animate-pulse"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "h-4 bg-gray-200 rounded w-1/4 mb-4"
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "h-3 bg-gray-200 rounded w-1/2"
    })));
  }
  const percentage = syncStatus?.total_reviews > 0 ? Math.round(syncStatus.synced_reviews / syncStatus.total_reviews * 100) : 0;
  const isDisabled = !productsAreSynced || syncing || syncStatus?.total_reviews === 0;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "px-6 py-5 border-b border-gray-200 bg-gray-50"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", {
    className: "text-xl font-semibold text-gray-900"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Review Sync', 'reviewapp-reviews'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "px-6 py-5 space-y-6"
  }, !productsAreSynced && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-yellow-50 border border-yellow-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", {
    className: "text-sm font-medium text-yellow-800"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Products Must Be Synced First', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-yellow-700 mt-1"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Please sync your products before syncing reviews. Reviews are linked to products.', 'reviewapp-reviews'))))), error && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-red-50 border border-red-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-red-600 mt-0.5",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-red-800"
  }, error)))), syncStatus?.is_syncing ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "space-y-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex items-center justify-between text-sm"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "font-medium text-gray-700"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Syncing reviews...', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "text-gray-600"
  }, syncStatus.synced_reviews, " / ", syncStatus.total_reviews)), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "relative"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "overflow-hidden h-4 text-xs flex rounded bg-gray-200"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      width: `${percentage}%`
    },
    className: "shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-600 transition-all duration-500"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-center mt-2 text-sm font-medium text-gray-700"
  }, percentage, "%")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-blue-50 border border-blue-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-blue-600 mt-0.5 animate-spin",
    fill: "none",
    viewBox: "0 0 24 24"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("circle", {
    className: "opacity-25",
    cx: "12",
    cy: "12",
    r: "10",
    stroke: "currentColor",
    strokeWidth: "4"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    className: "opacity-75",
    fill: "currentColor",
    d: "M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-blue-800"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Sync in progress. This may take a few minutes...', 'reviewapp-reviews')))))) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "space-y-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "grid grid-cols-3 gap-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-gray-50 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-2xl font-bold text-gray-900"
  }, syncStatus?.total_reviews || 0), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-sm text-gray-600"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Approved reviews', 'reviewapp-reviews'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-green-50 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-2xl font-bold text-green-900"
  }, syncStatus?.synced_reviews || 0), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-sm text-green-700"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Synced', 'reviewapp-reviews'))), syncStatus?.failed_reviews > 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-red-50 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-2xl font-bold text-red-900"
  }, syncStatus.failed_reviews), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-sm text-red-700"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed', 'reviewapp-reviews')))), syncStatus?.last_sync && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-gray-600"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Last synced:', 'reviewapp-reviews'), ' ', new Date(syncStatus.last_sync * 1000).toLocaleString()), syncStatus?.needs_sync && productsAreSynced ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-yellow-50 border border-yellow-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", {
    className: "text-sm font-medium text-yellow-800"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Sync Required', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-yellow-700 mt-1"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('You have approved reviews that need to be synced to ReviewApp.', 'reviewapp-reviews'))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    type: "button",
    onClick: startSync,
    disabled: isDisabled,
    className: "inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm border border-transparent rounded shadow-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
  }, syncing ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Starting sync...', 'reviewapp-reviews') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Sync Reviews', 'reviewapp-reviews'))) : productsAreSynced && !syncStatus?.needs_sync ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-green-50 border border-green-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-green-600 mt-0.5 flex-shrink-0",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-green-800"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All reviews are synced', 'reviewapp-reviews'))))) : null, !productsAreSynced && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-xs text-gray-500"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Sync products first to enable review sync', 'reviewapp-reviews')))));
}

/***/ }),

/***/ "./assets/src/js/components/SchemaPanel.jsx":
/*!**************************************************!*\
  !*** ./assets/src/js/components/SchemaPanel.jsx ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SchemaPanel)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);



function SchemaPanel({
  settings,
  onSave,
  saving
}) {
  const [enableSchema, setEnableSchema] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(window.reviewappAdmin?.enableSchema !== undefined ? window.reviewappAdmin.enableSchema : true);
  const [isSaving, setIsSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const handleToggle = async () => {
    setIsSaving(true);
    const newValue = !enableSchema;
    try {
      // Update WordPress option directly via admin-ajax
      const formData = new FormData();
      formData.append('action', 'reviewapp_update_schema_setting');
      formData.append('nonce', window.reviewappAdmin.nonce);
      formData.append('enable_schema', newValue ? '1' : '0');
      const response = await fetch(window.reviewappAdmin.ajaxUrl, {
        method: 'POST',
        body: formData
      });
      const result = await response.json();
      if (result.success) {
        setEnableSchema(newValue);
      } else {
        throw new Error(result.data || 'Failed to update setting');
      }
    } catch (err) {
      console.error('Failed to update schema setting:', err);
      alert((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to update schema setting. Please try again.', 'reviewapp-reviews'));
    } finally {
      setIsSaving(false);
    }
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-white rounded-lg shadow p-6"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex items-start justify-between"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex-1"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", {
    className: "text-lg font-semibold text-gray-900"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('SEO Schema Markup', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "mt-1 text-sm text-gray-600"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Enable Google-compliant structured data (JSON-LD schema) on product pages for rich snippets in search results.', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-blue-600 mt-0.5",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3 text-sm text-blue-800"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "font-medium"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('What is Schema Markup?', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("ul", {
    className: "mt-2 space-y-1"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", null, "\u2022 ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Displays star ratings in Google search results', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", null, "\u2022 ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Shows review counts and product information', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", null, "\u2022 ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Can increase click-through rates by up to 30%', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", null, "\u2022 ", (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Reviews are cached for 4 hours for optimal performance', 'reviewapp-reviews'))))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-6"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    type: "button",
    onClick: handleToggle,
    disabled: isSaving,
    className: `relative inline-flex flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${enableSchema ? 'bg-indigo-600' : 'bg-gray-200'} ${isSaving ? 'opacity-50 cursor-not-allowed' : ''}`,
    style: {
      height: '24px',
      width: '44px',
      padding: '0',
      margin: '0',
      verticalAlign: 'middle',
      boxSizing: 'border-box',
      lineHeight: '1'
    },
    role: "switch",
    "aria-checked": enableSchema
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: `pointer-events-none inline-block transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${enableSchema ? 'translate-x-5' : 'translate-x-0'}`,
    style: {
      height: '20px',
      width: '20px',
      display: 'block',
      margin: '0',
      boxSizing: 'border-box'
    }
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "mt-2 text-xs text-gray-500 text-right",
    style: {
      margin: '8px 0 0 0',
      fontSize: '12px',
      lineHeight: '1.4'
    }
  }, enableSchema ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Enabled', 'reviewapp-reviews') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Disabled', 'reviewapp-reviews')))), enableSchema && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "mt-4 pt-4 border-t border-gray-200"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-gray-600"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Schema markup is active on all WooCommerce product pages.', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "mt-3 space-y-2"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: "https://search.google.com/test/rich-results",
    target: "_blank",
    rel: "noopener noreferrer",
    className: "inline-flex items-center text-sm text-indigo-600 hover:text-indigo-700"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Test with Google Rich Results', 'reviewapp-reviews'), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "ml-1 w-4 h-4",
    fill: "none",
    stroke: "currentColor",
    viewBox: "0 0 24 24"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    strokeWidth: 2,
    d: "M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
  }))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "mx-2 text-gray-400"
  }, "\u2022"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: "https://validator.schema.org/",
    target: "_blank",
    rel: "noopener noreferrer",
    className: "inline-flex items-center text-sm text-indigo-600 hover:text-indigo-700"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Validate Schema', 'reviewapp-reviews'), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "ml-1 w-4 h-4",
    fill: "none",
    stroke: "currentColor",
    viewBox: "0 0 24 24"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    strokeWidth: 2,
    d: "M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
  }))))));
}

/***/ }),

/***/ "./assets/src/js/components/SettingsApp.jsx":
/*!**************************************************!*\
  !*** ./assets/src/js/components/SettingsApp.jsx ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SettingsApp)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _ConnectionPanel__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./ConnectionPanel */ "./assets/src/js/components/ConnectionPanel.jsx");
/* harmony import */ var _SyncPanel__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./SyncPanel */ "./assets/src/js/components/SyncPanel.jsx");
/* harmony import */ var _ReviewSyncPanel__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./ReviewSyncPanel */ "./assets/src/js/components/ReviewSyncPanel.jsx");
/* harmony import */ var _ReviewRequestPanel__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./ReviewRequestPanel */ "./assets/src/js/components/ReviewRequestPanel.jsx");
/* harmony import */ var _SchemaPanel__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./SchemaPanel */ "./assets/src/js/components/SchemaPanel.jsx");
/* harmony import */ var _LoadingSpinner__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./LoadingSpinner */ "./assets/src/js/components/LoadingSpinner.jsx");










function SettingsApp() {
  const [settings, setSettings] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [productSyncStatus, setProductSyncStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(true);
  const [saving, setSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    loadSettings();
    loadProductSyncStatus();
  }, []);
  const loadProductSyncStatus = async () => {
    try {
      const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/reviewapp/v1/sync/status'
      });
      setProductSyncStatus(data);
    } catch (err) {
      console.error('Failed to load product sync status:', err);
    }
  };
  const loadSettings = async () => {
    try {
      const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/reviewapp/v1/settings'
      });
      setSettings(data);
      setError(null);
    } catch (err) {
      console.error('Failed to load settings:', err);
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Failed to load settings', 'reviewapp-reviews'));
    } finally {
      setLoading(false);
    }
  };
  const saveSettings = async newSettings => {
    setSaving(true);
    setError(null);
    try {
      const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/reviewapp/v1/settings',
        method: 'POST',
        data: newSettings
      });
      setSettings(data);
    } catch (err) {
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Failed to save settings', 'reviewapp-reviews'));
      throw err;
    } finally {
      setSaving(false);
    }
  };
  if (loading) {
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_LoadingSpinner__WEBPACK_IMPORTED_MODULE_9__["default"], null);
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "max-w-4xl mx-auto py-8"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("header", {
    className: "mb-8"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h1", {
    className: "text-3xl font-bold text-gray-900"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('ReviewApp Settings', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "mt-2 text-gray-600"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Connect your WooCommerce store to ReviewApp for advanced review collection and display.', 'reviewapp-reviews'))), error && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "mb-6 bg-red-50 border border-red-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-red-600 mt-0.5",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-red-800"
  }, error)))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "space-y-6"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_ConnectionPanel__WEBPACK_IMPORTED_MODULE_4__["default"], {
    settings: settings,
    onSave: saveSettings,
    saving: saving
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_SyncPanel__WEBPACK_IMPORTED_MODULE_5__["default"], {
    isConnected: settings?.connection_status === 'connected'
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_ReviewSyncPanel__WEBPACK_IMPORTED_MODULE_6__["default"], {
    isConnected: settings?.connection_status === 'connected',
    productsAreSynced: productSyncStatus && !productSyncStatus.needs_sync
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_ReviewRequestPanel__WEBPACK_IMPORTED_MODULE_7__["default"], {
    settings: settings,
    onSave: saveSettings,
    saving: saving,
    isConnected: settings?.connection_status === 'connected'
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_SchemaPanel__WEBPACK_IMPORTED_MODULE_8__["default"], {
    settings: settings,
    onSave: saveSettings,
    saving: saving
  })));
}

/***/ }),

/***/ "./assets/src/js/components/StatusIndicator.jsx":
/*!******************************************************!*\
  !*** ./assets/src/js/components/StatusIndicator.jsx ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ StatusIndicator)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! classnames */ "./node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_2__);



function StatusIndicator({
  status,
  storeId
}) {
  const statusConfig = {
    connected: {
      color: 'green',
      icon: '●',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connected', 'reviewapp-reviews')
    },
    disconnected: {
      color: 'gray',
      icon: '●',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Not Connected', 'reviewapp-reviews')
    },
    error: {
      color: 'red',
      icon: '●',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Connection Error', 'reviewapp-reviews')
    }
  };
  const config = statusConfig[status] || statusConfig.disconnected;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: classnames__WEBPACK_IMPORTED_MODULE_2___default()('inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium', {
      'bg-green-100 text-green-800': config.color === 'green',
      'bg-gray-100 text-gray-800': config.color === 'gray',
      'bg-red-100 text-red-800': config.color === 'red'
    })
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "text-xs"
  }, config.icon), config.label);
}

/***/ }),

/***/ "./assets/src/js/components/SyncPanel.jsx":
/*!************************************************!*\
  !*** ./assets/src/js/components/SyncPanel.jsx ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ SyncPanel)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4__);





function SyncPanel({
  isConnected
}) {
  const [syncStatus, setSyncStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(true);
  const [syncing, setSyncing] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (isConnected) {
      loadSyncStatus();
      // Poll for status while syncing
      const interval = setInterval(() => {
        if (syncStatus?.is_syncing) {
          loadSyncStatus();
        }
      }, 2000); // Poll every 2 seconds

      return () => clearInterval(interval);
    }
  }, [isConnected, syncStatus?.is_syncing]);
  const loadSyncStatus = async () => {
    try {
      const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: '/reviewapp/v1/sync/status'
      });
      setSyncStatus(data);
      setError(null);
    } catch (err) {
      console.error('Failed to load sync status:', err);
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to load sync status', 'reviewapp-reviews'));
    } finally {
      setLoading(false);
    }
  };
  const startSync = async () => {
    setSyncing(true);
    setError(null);
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_4___default()({
        path: '/reviewapp/v1/sync/start',
        method: 'POST'
      });
      // Reload status immediately
      await loadSyncStatus();
    } catch (err) {
      setError(err.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed to start sync', 'reviewapp-reviews'));
    } finally {
      setSyncing(false);
    }
  };
  if (!isConnected) {
    return null;
  }
  if (loading) {
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "bg-white rounded-lg shadow-sm border border-gray-200 p-6"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "animate-pulse"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "h-4 bg-gray-200 rounded w-1/4 mb-4"
    }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "h-3 bg-gray-200 rounded w-1/2"
    })));
  }
  const percentage = syncStatus?.total_products > 0 ? Math.round(syncStatus.synced_products / syncStatus.total_products * 100) : 0;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "px-6 py-5 border-b border-gray-200 bg-gray-50"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h2", {
    className: "text-xl font-semibold text-gray-900"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Product Sync', 'reviewapp-reviews'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "px-6 py-5 space-y-6"
  }, error && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-red-50 border border-red-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-red-600 mt-0.5",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-red-800"
  }, error)))), syncStatus?.is_syncing ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "space-y-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex items-center justify-between text-sm"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "font-medium text-gray-700"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Syncing products...', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "text-gray-600"
  }, syncStatus.synced_products, " / ", syncStatus.total_products)), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "relative"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "overflow-hidden h-4 text-xs flex rounded bg-gray-200"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    style: {
      width: `${percentage}%`
    },
    className: "shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-600 transition-all duration-500"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-center mt-2 text-sm font-medium text-gray-700"
  }, percentage, "%")), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-blue-50 border border-blue-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-blue-600 mt-0.5 animate-spin",
    fill: "none",
    viewBox: "0 0 24 24"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("circle", {
    className: "opacity-25",
    cx: "12",
    cy: "12",
    r: "10",
    stroke: "currentColor",
    strokeWidth: "4"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    className: "opacity-75",
    fill: "currentColor",
    d: "M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-blue-800"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Sync in progress. This may take a few minutes...', 'reviewapp-reviews')))))) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "space-y-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "grid grid-cols-3 gap-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-gray-50 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-2xl font-bold text-gray-900"
  }, syncStatus?.total_products || 0), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-sm text-gray-600"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Products with reviews', 'reviewapp-reviews'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-green-50 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-2xl font-bold text-green-900"
  }, syncStatus?.synced_products || 0), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-sm text-green-700"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Synced', 'reviewapp-reviews'))), syncStatus?.failed_products > 0 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-red-50 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-2xl font-bold text-red-900"
  }, syncStatus.failed_products), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "text-sm text-red-700"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Failed', 'reviewapp-reviews')))), syncStatus?.last_sync && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-gray-600"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Last synced:', 'reviewapp-reviews'), ' ', new Date(syncStatus.last_sync * 1000).toLocaleString()), syncStatus?.needs_sync ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-yellow-50 border border-yellow-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", {
    className: "text-sm font-medium text-yellow-800"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Sync Required', 'reviewapp-reviews')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-yellow-700 mt-1"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('You have products with reviews that need to be synced to ReviewApp.', 'reviewapp-reviews'))))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("button", {
    type: "button",
    onClick: startSync,
    disabled: syncing || syncStatus?.total_products === 0,
    className: "inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm border border-transparent rounded shadow-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
  }, syncing ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Starting sync...', 'reviewapp-reviews') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Sync Products', 'reviewapp-reviews'))) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "bg-green-50 border border-green-200 rounded-lg p-4"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "flex"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("svg", {
    className: "w-5 h-5 text-green-600 mt-0.5 flex-shrink-0",
    fill: "currentColor",
    viewBox: "0 0 20 20"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("path", {
    fillRule: "evenodd",
    d: "M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z",
    clipRule: "evenodd"
  })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "ml-3"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "text-sm text-green-800"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('All products are synced', 'reviewapp-reviews'))))))));
}

/***/ }),

/***/ "./assets/src/scss/admin.scss":
/*!************************************!*\
  !*** ./assets/src/scss/admin.scss ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./node_modules/classnames/index.js":
/*!******************************************!*\
  !*** ./node_modules/classnames/index.js ***!
  \******************************************/
/***/ ((module, exports) => {

var __WEBPACK_AMD_DEFINE_ARRAY__, __WEBPACK_AMD_DEFINE_RESULT__;/*!
	Copyright (c) 2018 Jed Watson.
	Licensed under the MIT License (MIT), see
	http://jedwatson.github.io/classnames
*/
/* global define */

(function () {
	'use strict';

	var hasOwn = {}.hasOwnProperty;

	function classNames () {
		var classes = '';

		for (var i = 0; i < arguments.length; i++) {
			var arg = arguments[i];
			if (arg) {
				classes = appendClass(classes, parseValue(arg));
			}
		}

		return classes;
	}

	function parseValue (arg) {
		if (typeof arg === 'string' || typeof arg === 'number') {
			return arg;
		}

		if (typeof arg !== 'object') {
			return '';
		}

		if (Array.isArray(arg)) {
			return classNames.apply(null, arg);
		}

		if (arg.toString !== Object.prototype.toString && !arg.toString.toString().includes('[native code]')) {
			return arg.toString();
		}

		var classes = '';

		for (var key in arg) {
			if (hasOwn.call(arg, key) && arg[key]) {
				classes = appendClass(classes, key);
			}
		}

		return classes;
	}

	function appendClass (value, newClass) {
		if (!newClass) {
			return value;
		}
	
		if (value) {
			return value + ' ' + newClass;
		}
	
		return value + newClass;
	}

	if ( true && module.exports) {
		classNames.default = classNames;
		module.exports = classNames;
	} else if (true) {
		// register as 'classnames', consistent with npm package name
		!(__WEBPACK_AMD_DEFINE_ARRAY__ = [], __WEBPACK_AMD_DEFINE_RESULT__ = (function () {
			return classNames;
		}).apply(exports, __WEBPACK_AMD_DEFINE_ARRAY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
	} else // removed by dead control flow
{}
}());


/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

"use strict";
module.exports = window["React"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
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
/*!********************************!*\
  !*** ./assets/src/js/admin.js ***!
  \********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _scss_admin_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../scss/admin.scss */ "./assets/src/scss/admin.scss");
/* harmony import */ var _components_SettingsApp__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/SettingsApp */ "./assets/src/js/components/SettingsApp.jsx");

/**
 * ReviewApp Admin JavaScript
 */




document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('reviewapp-settings-root');
  if (root) {
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.render)((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_SettingsApp__WEBPACK_IMPORTED_MODULE_3__["default"], null), root);
  }
});
})();

/******/ })()
;
//# sourceMappingURL=admin.js.map