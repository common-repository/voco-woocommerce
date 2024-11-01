var logPrevInner = document.getElementById("logviewerinner");
function onClickShowLogs(e) {
  document.body.setAttribute("style", "width: 100%;height: 100%;overflow: hidden;");
  handleLoggerBox(false);
  if (logPrevInner)
    logPrevInner.innerHTML = "";

  getPluginLogs()
    .then(function (json) {
      if (logPrevInner && json)
        logPrevInner.innerHTML = json.Log;

      hideShowLoader(true);

      if (logContainer)
        logContainer.scrollTop = logContainer.scrollHeight;
    });
}

function onClickSendReport() {
  handleLoggerBox(false, true);
  sendReport()
    .then(function (response) {
      console.log(response);
      if (response && response.Status === "Error" && response.Error)
        addOrDeleteMessage(response.Error, true);
      else
        addOrDeleteMessage("Report sent successfully");
      handleLoggerBox(true, true);
    });
}

function getPluginLogs() {
  return fetch(ajaxurl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
    },
    body: 'action=get_plugin_logs',
    credentials: 'same-origin'
  })
    .then(function (response) {
      return response.json();
    })
    .catch(function (err) {
      handleLoggerBox(true);
      addOrDeleteMessage("Some error occurred please try again later", true);
      console.error("lets check why this happended", err);
    });
}

function sendReport() {
  return fetch(ajaxurl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
    },
    body: 'action=send_plugin_logs',
    credentials: 'same-origin'
  })
    .then(function (response) {
      return response.json();
    })
    .catch(function (err) {
      handleLoggerBox(true, true);
      addOrDeleteMessage("Some error occurred please try again later", true);
      console.error("lets check why this happended", err);
    });
}

var logPrevContainer = document.querySelector(".logviewer_container");
var logViewer = document.querySelector("#logviewer");
var logContainer = document.querySelector(".log_container");
var loader = document.querySelector(".loader");

function handleLoggerBox(isHide, isView) {
  if (isHide)
    document.body.removeAttribute("style");

  if (logPrevContainer)
    logPrevContainer.style.display = !isHide ? "block" : "none";

  if (logContainer)
    logContainer.scrollTop = logContainer.scrollHeight;

  if (logViewer && isView)
    logViewer.style.display = isHide ? "block" : "none";

  hideShowLoader(isHide);
}

function hideShowLoader(isHide) {
  if (loader)
    loader.setAttribute(
      "class",
      isHide ?
        (loader.getAttribute("class") || "").replace(" animate", "")
        : (loader.getAttribute("class") || "") + " animate"
    );
}

var vwpMessagesEl = document.querySelector('#vwp-messages');
function addOrDeleteMessage(message, isError) {
  var id = "vwp-error-msg" + getRandomInt(100000);

  var div = document.createElement("div");
  div.setAttribute("class", (isError ? "error" : "updated") + " notice vwp-notice")
  div.setAttribute("id", id);

  var p = document.createElement("p");
  p.innerText = message;

  div.appendChild(p);
  if (vwpMessagesEl)
    vwpMessagesEl.appendChild(div);
  setTimeout(function () { div.remove(); }, 10000);
}

function getRandomInt(max) {
  return Math.floor(Math.random() * Math.floor(max));
}