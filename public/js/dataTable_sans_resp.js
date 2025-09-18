"use strict";

// Class definition
var KTAppDataTableSimple = function () {
    // Shared variables
    var table;
    var datatable;

    // Private functions
    var initDatatable = function () {
        // Init datatable --- more info on datatables: https://datatables.net/manual/
        datatable = $(table).DataTable({
            pageLength: 10,
            info: false,
            fixedColumns: {
                left:1
            },
            scrollCollapse: true,
            scrollX: true,
            lengthChange: false,
            showSearch:false,
            /*"info": false,
            'order': [],
            'pageLength': 10,
            'columnDefs': [
                { render: DataTable.render.number(',', '.', 2), targets: 4},
                { orderable: false, targets: 0 }, // Disable ordering on column 0 (checkbox)
                { orderable: false, targets: 7 }, // Disable ordering on column 7 (actions)
            ]*/
        });
    }

    // Search Datatable --- official docs reference: https://datatables.net/reference/api/search()
    var handleSearchDatatable = () => {
        const filterSearch = document.querySelector('[data-kt-dataTable-filter="search"]');
        filterSearch.addEventListener('keyup', function (e) {
            datatable.search(e.target.value).draw();
        });
    }

    // Handle status filter dropdown
    var handleStatusFilter = () => {
        const filterStatus = document.querySelector('[data-kt-dataTable-filter="filtre"]');
        $(filterStatus).on('change', e => {
            let value = e.target.value;
            if(value === 'all'){
                value = '';
            }
            datatable.column(colonne_filtre).search(value).draw();
        });
    }

    // Public methods
    return {
        init: function () {
            table = document.querySelector('#kt_dataTable');

            if (!table) {
                return;
            }
            initDatatable();
            handleSearchDatatable();
            handleStatusFilter();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTAppDataTableSimple.init();
});
