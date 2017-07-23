
			$editcell = '';
                        // bug corrected by G. Schwed (DUK), 2014-10-24: added "&courseid='.$r->courseid.'" in following line
			$editcell .= '<a title="'.$stredit.'"  href="editreport.php?id='.$r->id.'&courseid='.$r->courseid.'"><img src="'.$OUTPUT->pix_url('/t/edit').'" class="iconsmall" alt="'.$stredit.'" /></a>&nbsp;&nbsp;';
			$editcell .= '<a title="'.$strdelete.'"  href="editreport.php?id='.$r->id.'&amp;delete=1&amp;sesskey='.$USER->sesskey.'"><img src="'.$OUTPUT->pix_url('/t/delete').'" class="iconsmall" alt="'.$strdelete.'" /></a>&nbsp;&nbsp;';


			if (!empty($r->visible)) {
				$editcell .= '<a title="'.$strhide.'" href="editreport.php?id='.$r->id.'&amp;hide=1&amp;sesskey='.$USER->sesskey.'">'.'<img src="'.$OUTPUT->pix_url('/t/hide').'" class="iconsmall" alt="'.$strhide.'" /></a> ';}
			else {
				$editcell .= '<a title="'.$strshow.'" href="editreport.php?id='.$r->id.'&amp;show=1&amp;sesskey='.$USER->sesskey.'">'.'<img src="'.$OUTPUT->pix_url('/t/show').'" class="iconsmall" alt="'.$strshow.'" /></a> ';
			}
