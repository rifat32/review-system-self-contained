<!DOCTYPE html>
<html>
<head>
    <title>Business List</title>

    <!--ALL CUSTOM FUNCTIONS -->
    @php
        // Define a function within the Blade file
        function processString($inputString) {
            // Remove underscore
            $withoutUnderscore = str_replace('_', '', $inputString);

            // Remove everything from the pound sign (#) and onwards
            $finalString = explode('#', $withoutUnderscore)[0];

            // Capitalize the string
            $capitalizedString = ucwords($finalString);

            return $capitalizedString;
        }

        function format_date($date) {
            return \Carbon\Carbon::parse($date)->format('d-m-Y');
        }
    @endphp

    @php
       $color  = env("FRONT_END_VERSION") == "red" ? "#dc2b28" : "#335ff0";
    @endphp

    <style>
        /* Add any additional styling for your PDF */
        body {
            font-family: Arial, sans-serif;
            margin:0;
            padding:0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size:10px;
        }
        .table_head_row {
            color:#fff;
            background-color:{{$color}};
            font-weight:600;
        }
        .table_head_row td {
            color:#fff;
        }
        .table_head_row th, tbody tr td {
            text-align: left;
            padding:10px 0px;
        }
        .table_row {
            background-color:#ffffff;
        }
        .table_row td {
            padding:10px 0px;
            border-bottom:0.2px solid #ddd;
        }

        .logo {
            width:75px;
            height:75px;
        }
        .file_title {
            font-size:1.3rem;
            font-weight:bold;
            text-align:right;
        }
        .business_name {
            font-size:1.2rem;
            font-weight:bold;
            display:block;
        }
        .business_address {
        }
    </style>

</head>
<body>

    <table style="margin-top:-30px">
        <tbody>
            <tr>
                @php
                    $logo_path = public_path($business->logo); // Get the full path of the logo
                @endphp

                @if ($business->logo && file_exists($logo_path))
                    <td rowspan="2">
                        <img class="logo" src="{{ asset($business->logo) }}">
                    </td>
                @else
                    <td rowspan="2">
                        <div class="css-logo">
                            {{ $business->name }}
                        </div>
                    </td>
                @endif
                <td></td>
            </tr>
            <tr>
                <td class="file_title">Business List</td>
            </tr>
            <tr>
                <td>
                    <span class="business_name">{{ $business->name }}</span>
                    <address class="business_address">{{ $business->address }}</address>
                </td>
            </tr>
        </tbody>
    </table>

    <table>
        <h3>Business Details</h3>
        <thead>
            <tr class="table_head_row">
                <th class="index_col"></th>
                <th>Business Name</th>
                <th>Registration Date</th>
                <th>Owner Name</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>

            @if (count($businesses))
                @foreach ($businesses as $index => $business)
                    <tr class="table_row">
                        <td class="index_col">{{ $index + 1 }}</td>
                        <td>{{ $business->Name }}</td>
                        <td>{{ format_date($business->created_at) }}</td>
                        <td>{{ $business->owner->first_Name . " " . $business->owner->last_Name}}</td>
                        <td>{{ $business->Status }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="5" style="text-align: center;">No Data Found</td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
