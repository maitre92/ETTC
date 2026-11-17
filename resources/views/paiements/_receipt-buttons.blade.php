{{-- Boutons du reçu PDF : A5 (feuille) et ticket thermique 80 mm, ouverts dans un onglet pour être imprimés.
     $receiptRoute (nom de route), $paiement, $btn (classes du bouton). --}}
<a href="{{ route($receiptRoute, ['paiement' => $paiement, 'format' => 'a5', 'imprimer' => 1]) }}" class="{{ $btn }}" target="_blank" rel="noopener" title="Reçu A5 à imprimer"><i class='bx bx-file' aria-hidden="true"></i><span class="ms-1">A5</span></a>
<a href="{{ route($receiptRoute, ['paiement' => $paiement, 'format' => 'thermique', 'imprimer' => 1]) }}" class="{{ $btn }}" target="_blank" rel="noopener" title="Ticket pour imprimante thermique (80 mm)"><i class='bx bx-receipt' aria-hidden="true"></i><span class="ms-1">Ticket</span></a>
