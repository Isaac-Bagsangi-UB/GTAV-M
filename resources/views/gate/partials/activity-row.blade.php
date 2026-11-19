{{-- One ticket's gate activity. scanner.js builds the same markup for live updates. --}}
<li class="activity-row activity-{{ $row['status'] }}" data-key="{{ $row['key'] }}">
    <div class="activity-person">
        <strong>{{ $row['name'] }}</strong>
        <span>{{ $row['company'] }}</span>
    </div>
    <div class="activity-status">
        <span class="badge badge-{{ $row['status'] }}">{{ $row['status_label'] }}</span>
        <span class="activity-detail">{{ $row['detail'] }}</span>
        @if ($row['repeat_note'])
            <span class="activity-repeat">{{ $row['repeat_note'] }}</span>
        @endif
    </div>
</li>
