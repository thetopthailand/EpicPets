package be.feeps.epicpets;

import be.feeps.epicpets.utils.MessageUtil;
import org.bukkit.entity.Player;

/**
 * Created by feeps on 21/04/2017.
 */
public enum EpicPermissions {

    SPAWNPET("epicpets.pet.spawn"),
    REMOVEPET("epicpets.pet.remove"),
    RENAMEPET("epicpets.pet.rename"),

    OPENGUIMAIN("epicpets.gui.open.main"),
    OPENGUIANIMATIONS("epicpets.gui.open.animations"),
    OPENGUIPREFERENCES("epicpets.gui.open.preferences"),

    OPENGUIHELMET("epicpets.gui.open.helmet"),
    OPENGUICHESTPLATE("epicpets.gui.open.chestplate"),
    OPENGUILEGGINGS("epicpets.gui.open.leggings"),
    OPENGUIBOOTS("epicpets.gui.open.boots"),

    PREFERENCESSETSMALL("epicpets.gui.action.preferences.setsmall"),
    PREFERENCESSHOWNAME("epicpets.gui.action.preferences.showname"),
    PREFERENCESSETVISIBLE("epicpets.gui.action.preferences.setvisible"),
    PREFERENCESSHOWARMS("epicpets.gui.action.preferences.showarms"),
    PREFERENCESSNEAKPROTECT("epicpets.gui.action.preferences.sneakprotect");

    private String perm;
    EpicPermissions(String perm){
        this.perm = perm;
    }

    public String getPerm(){
        return this.perm;
    }

    public boolean hasPerm(Player player){
        if(player.hasPermission(this.perm)){
            return true;
        }else{
            player.sendMessage(MessageUtil.translate(Main.getI().getMsgCfg().noPerm));
            return false;
        }
    }
}