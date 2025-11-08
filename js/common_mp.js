document.addEventListener("DOMContentLoaded", function () {
    const currentFile = window.location.pathname.split("/").pop(); // gets 'mp_form.html'
    const links = document.querySelectorAll(".offcanvas-body a");

    links.forEach(link => {
      const hrefFile = link.getAttribute("href").split("/").pop(); // also gets 'mp_form.html'
      if (hrefFile === currentFile) {
        link.classList.add("active");
      }
    });
  });
  const csrfToken = '12345';
let alertBoxDelay;

flatpickr(".datepick", {
    dateFormat: "d/m/Y",
    allowInput: true
});

let lastErrorMessage = ''; // store last error temporarily

document.getElementById('show-last-error').addEventListener('click', () => {
    if (lastErrorMessage) {
        alertMessage(lastErrorMessage, 'e');
    } else {
        alertMessage('No recent error found.', 'i');
    }
});


function showConfirm(message, onConfirm) {
    const modal = document.getElementById('confirmModal');
    const text = document.getElementById('confirmText');
    const save = document.getElementById('save');
    const cancel = document.getElementById('cancel');
  
    text.textContent = message || 'Are you sure?';
    modal.style.display = 'flex';
  
    // cleanup previous listeners
    const cleanup = () => {
      modal.style.display = 'none';
      save.replaceWith(save.cloneNode(true));
      cancel.replaceWith(cancel.cloneNode(true));
    };
  
    // bind fresh listeners
    save.addEventListener('click', () => {
      cleanup();
      if (typeof onConfirm === 'function') onConfirm();
    });
    cancel.addEventListener('click', cleanup);
  }
  
    // Keyboard shortcuts: Alt + S for Save, Enter for confirmation in modal, Esc for cancel
    document.addEventListener('keydown', function(e) {
        // Alt + S to trigger Save button
        if (e.altKey && e.key.toLowerCase() === 's') {
          e.preventDefault();
          document.getElementById('submit').click();
        }
        // Enter to confirm Save if modal is open
        if (e.key === 'Enter' && document.getElementById('confirmModal').style.display === 'flex') {
          e.preventDefault();
          document.getElementById('save').click();
        }
        // Esc to cancel
        // if (e.key === 'Escape') {
        //   e.preventDefault();
        //   document.querySelector('button[type="reset"]').click();
        // }
      });
 
      // Cancel confirmation
      document.getElementById('cancel').addEventListener('click', function() {
        document.getElementById('confirmModal').style.display = 'none';
      });




// ================Refer  ==========
 //<script type="text/javascript" src="../../components/minerva/mscript.js"></script>

function api_httpError(error) {
    const status = error?.response?.status || error.status;
    const statusText = error?.response?.statusText || error.statusText;

    const errorMessages = {
        401: "Unauthorized: Please log in again.",
        403: "Forbidden: You do not have permission to perform this action.",
        404: "Resource not found. Please check your request.",
        405: "Method Not Allowed",
        500: "Server Error: Please contact your System Administrator."
    };

    if (status) {
        // Handle 404 separately for session expiration
        if (status === 404) {
            if (!getCookie('MSOERPMSOID')) {
                alertMessage('Session already expired.', 'e');
                setTimeout(() => window.location = "../../index.php", 3000);
            } else {
                alertMessage('Sorry! Please try again later. (Ref: Session Expired)', 'e');
            }
        } else {
            // Use the error message from the map or a default message
            const message = errorMessages[status] || `An error occurred (HTTP ${status}: ${statusText}). Please try again.`;
            alertMessage(message, 'e');
        }

        // Redirect for 401 Unauthorized
        if (status === 401) {
            setTimeout(() => window.location = "../../index.php", 3000);
        }
    } else if (error.message === 'Failed to fetch') {
        // Handle network errors
        alertMessage("Network connectivity failed. Please try again later.", 'e');
    } else {
        // Handle unexpected errors
        console.error("Unexpected Error:", error);
        alertMessage("Sorry! An uncaught exception occurred.", 'e');
    }
}

function displayLoader(type) {
    // Remove any existing loader
    document.querySelectorAll('body .page-loader').forEach(el => el.remove());

    if (type.trim() === 'show' || type.trim() === '') {
        const loaderDiv = document.createElement('div');
        loaderDiv.className = 'page-loader';

        const spinner = document.createElement('i');
        spinner.className = 'fa fa-spinner fa-3x fa-pulse';

        loaderDiv.appendChild(spinner);
        document.body.appendChild(loaderDiv);
    }
}

function alertMessage(msg, type, redirect_to, delay_ms) {
    clearTimeout(alertBoxDelay);

    if (type && msg) {
        const alert_ele = document.getElementById('alert-message');
        const last_err = document.querySelector('.show-last-error');
        const msg_ele = document.getElementById('alert-msg');

        alert_ele.classList.remove('alert-success', 'alert-danger', 'alert-info', 'active');
        msg_ele.innerHTML = '';

        if (type === 's') {
            if (last_err) last_err.style.display = '';
            if (msg) {
                alert_ele.classList.add('alert-success', 'active');
                msg_ele.innerHTML = '<span><i class="fas fa-check-circle"></i></span>  ' + msg;
            }

            if (redirect_to && redirect_to === '1') {
                alertBoxDelay = setTimeout(() => {
                    alert_ele.classList.remove('active');
                    window.location.reload(true);
                }, delay_ms || 1500);
            } else if (redirect_to && redirect_to !== '1') {
                alertBoxDelay = setTimeout(() => {
                    alert_ele.classList.remove('active');
                    window.location = redirect_to;
                }, delay_ms || 1500);
            } else {
                alertBoxDelay = setTimeout(() => {
                    alert_ele.classList.remove('active');
                }, delay_ms || 1500);
            }

        } else if (type === 'e' && msg) {
            alert_ele.classList.add('alert-danger', 'active');
            msg_ele.innerHTML = '<span><i class="fas fa-times-circle"></i></span>  ' + msg;
    // Save last error
    lastErrorMessage = msg;
    document.getElementById('show-last-error').style.display = 'inline-block';
            alertBoxDelay = setTimeout(() => {
                alert_ele.classList.remove('active');
            }, delay_ms || 6000);
        }
        else if (type === 'i' && msg) {
            alert_ele.classList.add('alert-info', 'active');
            msg_ele.innerHTML = '<span><i class="fas fa-exclamation-circle"></i></span>  ' + msg;
            alertBoxDelay = setTimeout(() => {
                alert_ele.classList.remove('active');
            }, delay_ms || 2000);
        }
    }
}

//   ========================   Export to Excel   ============================
function exportTableToExcel(tableId, reportName = "Report",excludeFromExport=[]) {
   const table = document.getElementById(tableId);
   if (!table) {
       alertMessage("Report table not found.",'e');
       return;
       console.log(table);
   }

   const rows = table.querySelectorAll("tr");
   if (rows.length <= 1) {
     alertMessage('No data to export. Please search for a report first.','e');
     return;
   }

   // Define columns to exclude, e.g., "Action" or customize as needed
   const columnsToNeglect = excludeFromExport;

   // Assuming report name is passed or set globally
   const reportNameParam = reportName;

   const headerCells = rows[0].querySelectorAll("th");
   const headers = [];
   const headerIndicesToInclude = [];

   headerCells.forEach((th, index) => {
     const headerText = th.textContent.trim();
     if (!columnsToNeglect.includes(headerText)) {
       headers.push(headerText);
       headerIndicesToInclude.push(index);
    //    console.log(`Including column: ${headerText} at index ${index}`);
     }
   });

   const data = [];
   for (let i = 1; i < rows.length; i++) {
     const row = rows[i];
     const cells = row.querySelectorAll("td");
     const rowData = {};
     let isEmptyRow = true;

     headerIndicesToInclude.forEach((colIndex, outputIndex) => {
       if (cells[colIndex]) {
         const cellContent = cells[colIndex].textContent.trim();
         rowData[headers[outputIndex]] = cellContent;
         if (cellContent) isEmptyRow = false;
        //  console.log(`Row ${i}, Col ${headers[outputIndex]}:`,cellContent);
       }
     });

     if (!isEmptyRow) {
       data.push(rowData);
     }
   }

   if (data.length === 0) {
     alertMessage('No valid data to export.', "e");
     return;
   }

   const wb = XLSX.utils.book_new();
   const ws = XLSX.utils.json_to_sheet(data);
   XLSX.utils.book_append_sheet(wb, ws, reportNameParam);
   XLSX.writeFile(wb, `${reportNameParam}_${new
  Date().toISOString().slice(0, 10)}.xlsx`);
}