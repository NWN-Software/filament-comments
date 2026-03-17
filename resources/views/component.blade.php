<livewire:comments 
    :record="$record ?? $this->record" 
    :resource="$resource" 
    :sentMailWhenTagged="$sentMailWhenTagged ?? false"
    :sendMailWhenTagged="$sendMailWhenTagged ?? false"
    :mailSubjectForTaggedUsers="$mailSubjectForTaggedUsers ?? ''"
/>