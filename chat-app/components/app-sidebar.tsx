"use client"

import { useState } from "react"
import { MessageSquarePlus, RefreshCw, MessageCircle, Trash2, BookOpen } from "lucide-react"
import { toast } from "sonner"
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"

export function AppSidebar({ chats, fetchChats, createChat, loadChat, deleteChat, activeChatId, user, onLogout }: any) {
  const [pendingDeleteId, setPendingDeleteId] = useState<string | null>(null)
  const [pendingDeleteTitle, setPendingDeleteTitle] = useState<string>("")

  const handleDeleteClick = (e: React.MouseEvent, chat: any) => {
    e.stopPropagation()
    e.preventDefault()
    setPendingDeleteId(chat.id || chat.uuid)
    setPendingDeleteTitle(chat.title || "New Session")
  }

  const handleConfirmDelete = async () => {
    if (!pendingDeleteId) return
    try {
      await deleteChat(pendingDeleteId)
      toast.success("Chat deleted", {
        description: `"${pendingDeleteTitle}" has been removed.`,
      })
    } catch {
      toast.error("Failed to delete chat", {
        description: "Something went wrong. Please try again.",
      })
    } finally {
      setPendingDeleteId(null)
    }
  }

  return (
    <>
      <Sidebar className="border-r border-border/50">
        <SidebarContent className="bg-muted/20">
          {/* Branding */}
          <div className="px-5 pt-6 pb-4">
            <div className="flex items-center gap-2">
              <div className="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center">
                <span className="text-primary font-bold text-xs">N</span>
              </div>
              <span className="font-semibold text-sm tracking-wide">Nexus AI</span>
            </div>
          </div>

          {user ? (
            <>
              {/* New Session button */}
              <SidebarGroup className="pt-0 px-4 pb-4">
                <SidebarMenu>
                  <SidebarMenuItem>
                    <SidebarMenuButton
                      onClick={createChat}
                      className="bg-background shadow-sm border border-border/60 hover:bg-muted font-medium transition-all h-10 px-3 cursor-pointer rounded-xl w-full justify-start"
                    >
                      <div className="bg-primary/10 rounded-full p-1 text-primary shadow-sm mr-2">
                        <MessageSquarePlus className="h-3.5 w-3.5" />
                      </div>
                      <span className="text-sm">New Session</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                  {user?.role === 'admin' && (
                    <SidebarMenuItem>
                      <SidebarMenuButton
                        render={<a href="/documents" />}
                        className="hover:bg-muted font-medium transition-all h-10 px-3 cursor-pointer rounded-xl w-full justify-start"
                      >
                        <div className="bg-blue-500/10 rounded-full p-1 text-blue-500 shadow-sm mr-2">
                          <BookOpen className="h-3.5 w-3.5" />
                        </div>
                        <span className="text-sm">Knowledge Base</span>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  )}
                </SidebarMenu>
              </SidebarGroup>

              {/* Chat list */}
              <SidebarGroup className="flex-1 min-h-0">
                <div className="flex justify-between items-center pr-4 pl-2 pb-3">
                  <SidebarGroupLabel className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                    Recent Chats
                  </SidebarGroupLabel>
                  <button onClick={fetchChats} title="Refresh" className="text-muted-foreground hover:text-foreground">
                    <RefreshCw className="h-3.5 w-3.5" />
                  </button>
                </div>
                <SidebarGroupContent>
                  <SidebarMenu className="gap-0.5 px-2">
                    {chats.map((chat: any) => {
                      const chatId = chat.id || chat.uuid;
                      return (
                      <SidebarMenuItem key={chatId}>
                        <SidebarMenuButton
                          onClick={() => loadChat(chatId)}
                          isActive={activeChatId === chatId}
                          className={`group rounded-lg cursor-pointer transition-colors h-9 w-full justify-start pr-1 ${
                            activeChatId === chatId
                              ? "bg-primary/5 text-primary font-medium"
                              : "text-muted-foreground hover:text-foreground hover:bg-muted/80"
                          }`}
                        >
                          <MessageCircle className="h-4 w-4 shrink-0 opacity-70 mr-2" />
                          <span className="truncate text-[13.5px] tracking-tight flex-1 text-left">
                            {chat.title || "New Session"}
                          </span>
                          <span
                            role="button"
                            tabIndex={0}
                            onClick={(e) => handleDeleteClick(e, chat)}
                            onKeyDown={(e) => e.key === 'Enter' && handleDeleteClick(e as any, chat)}
                            title="Delete chat"
                            className="shrink-0 p-1 rounded transition-all opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive ml-auto"
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </span>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    )})}
                    {chats.length === 0 && (
                      <SidebarMenuItem>
                        <span className="text-xs text-muted-foreground/60 px-3 py-2 italic block">No chats yet.</span>
                      </SidebarMenuItem>
                    )}
                  </SidebarMenu>
                </SidebarGroupContent>
              </SidebarGroup>
            </>
          ) : (
            <SidebarGroup className="flex-1 min-h-0 px-4">
              <div className="flex flex-col items-center text-center justify-center h-full space-y-4 opacity-80 pt-10">
                <MessageCircle className="w-10 h-10 text-muted-foreground mb-2" />
                <h3 className="text-sm font-semibold">Log in to save chats</h3>
                <p className="text-xs text-muted-foreground max-w-[200px]">
                  Your current chat is temporary. Sign up or log in to save your history and access more features.
                </p>
              </div>
            </SidebarGroup>
          )}
          
          {/* User Profile Footer */}
          {user && (
            <div className="mt-auto border-t border-border/50 p-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 overflow-hidden">
                  <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                    <span className="text-primary font-bold text-xs">
                      {user.name?.charAt(0).toUpperCase()}
                    </span>
                  </div>
                  <div className="flex flex-col overflow-hidden">
                    <span className="text-sm font-medium truncate">{user.name}</span>
                    <span className="text-xs text-muted-foreground truncate">{user.email}</span>
                  </div>
                </div>
                <button
                  onClick={onLogout}
                  className="text-muted-foreground hover:text-destructive p-2 rounded-md transition-colors"
                  title="Logout"
                >
                  <Trash2 className="h-4 w-4" /> {/* Or use a LogOut icon if imported */}
                </button>
              </div>
            </div>
          )}
        </SidebarContent>
      </Sidebar>

      {/* AlertDialog for delete confirmation */}
      <AlertDialog open={!!pendingDeleteId} onOpenChange={(open) => { if (!open) setPendingDeleteId(null) }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Chat Session?</AlertDialogTitle>
            <AlertDialogDescription>
              This will permanently delete{" "}
              <span className="font-medium text-foreground">"{pendingDeleteTitle}"</span>{" "}
              and all its messages. This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleConfirmDelete}
              className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
            >
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
