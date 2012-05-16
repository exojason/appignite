<?php
interface RelationshipType
{
    const BelongsTo = "BelongsTo";
    const HasOne = "HasOne";
    const HasMany = "HasMany";
    const ManyToMany = "ManyToMany";
    const IsReferencedBy = "IsReferencedBy";
    const References = "References";
}
?>