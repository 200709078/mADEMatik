<?php
namespace App\Http\Controllers;
use App\Models\ContactMessage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ContactController extends Controller
{
    public function store(Request $request,NotificationService $notifications)
    {
        $data=$request->validate(['name'=>['required','string','min:2','max:120'],'email'=>['required','email','max:254'],'subject'=>['required','string','min:3','max:180'],'body'=>['required','string','min:10','max:5000'],'website'=>['nullable','max:0']]);
        DB::transaction(function() use($data,$notifications) {
            ContactMessage::create(collect($data)->only(['name','email','subject','body'])->all());
            $notifications->superUsers('contact_message','Yeni iletişim mesajı','İletişim formundan yeni bir mesaj geldi.',route('admin.contact-messages.index'));
        });
        return back()->with('status','Mesajınız alındı. Teşekkür ederim.');
    }
}
