<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{asset('public/assets/admin-module/css/bootstrap.min.css')}}" />
    <link rel="stylesheet" href="{{asset('public/assets/admin-module/css/style.css')}}" />
</head>

<body>
<div class="container">
<div class="" id="printableTable">
    <div class="row mb-4">
        <h4 class="col-12 fw-medium text-primary mb-2">{{ translate('safety_alert_list') }}</h4>
    </div>
    <table class="table table-borderless table-striped">
        <thead>
        <tr>
            <th class="text-uppercase text-primary text-center">{{ translate('SL')}}</th>
            <th class="text-uppercase text-primary text-center">{{ translate('alert_ID')}}</th>
            <th class="text-uppercase text-primary text-center">{{ translate('trip_ID')}}</th>
            <th class="text-uppercase text-primary text-center">{{ translate('date')}}</th>
            <th class="text-uppercase text-primary text-center">{{ translate('sent_by')}}</th>
            <th class="text-uppercase text-primary text-center">{{ translate('status')}}</th>
            <th class="text-uppercase text-primary text-center">{{ translate('solved_by')}}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($data as $key => $d)
        <tr>
            <td class="text-center">{{++$key}}</td>
            <td class="text-center">{{$d['alert_id'] ?? 'N/A'}}</td>
            <td class="text-center">{{$d['trip_id'] ?? 'N/A'}}</td>
            <td class="text-center">{{$d['date'] ?? 'N/A'}}</td>
            <td class="text-center">{{$d['sent_by'] ?? 'N/A'}}</td>
            <td class="text-center">{{$d['status'] ?? 'N/A'}}</td>
            <td class="text-center">{{$d['solved_by'] ?? 'N/A'}}</td>
        </tr>
        @endforeach

        </tbody>
    </table>
    <p>{{ translate('note:_this_is_software_generated_copy')}}</p>
</div>
</div>
<iframe name="print_frame" width="0" height="0" frameborder="0" src="about:blank"></iframe>
</body>
</html>

<script>
    window.frames["print_frame"].document.body.innerHTML = document.getElementById("printableTable").innerHTML;
    window.frames["print_frame"].window.focus();
    window.frames["print_frame"].window.print();
</script>
