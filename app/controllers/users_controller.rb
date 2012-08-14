class UsersController < ApplicationController
	def show
		render :json => params
		# @user = AsUser.find_by_name(params[:id])
		# render :json => @user.extension
		# render :json => 
	end
end
