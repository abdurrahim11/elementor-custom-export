jQuery(document).ready(function($) {
    $(document).on('click', '#custom-export', function(e) {
        e.preventDefault();

        let hashParams = new URLSearchParams(window.location.hash.substring(2)); // Remove #/

        // Convert URLSearchParams to a regular object
        let data = {};
        hashParams.forEach((value, key) => {
            data[key] = value;
        });
        
        $.ajax({
            url: customExport.ajax_url,
            method: 'GET',
            data: data,
            headers: {
            },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    // Create a Blob from the CSV content
                    var blob = new Blob([response.csv_content], { type: 'text/csv;charset=utf-8;' });
                    var url = URL.createObjectURL(blob);

                    // Create a temporary link element to download the CSV file
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'export.csv';
                    document.body.appendChild(a);
                    a.click();

                    // Clean up
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    alert('An error occurred while exporting the data.');
                }
            },
            error: function(response) {
                alert('An error occurred while exporting the data.');
            }
        });
    });
});

